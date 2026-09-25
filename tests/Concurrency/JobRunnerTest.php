<?php
declare(strict_types=1);

namespace Tests\Concurrency;

use Tests\TestCase;
use App\Core\JobRunner;

class TestJob
{
    public function __invoke()
    {
        // empty task
    }
}

class TestExceptionJob
{
    public function __invoke()
    {
        throw new \Exception('Test crash');
    }
}

class JobRunnerTest extends TestCase
{
    public function testJobRunnerExecutesSuccessfullyAndReleasesLock(): void
    {
        $runner = new JobRunner($this->pdo);
        $runner->run('test_job', new TestJob());
        
        // Lock should be released (deleted or nullified? In JobRunner it deletes the row on success)
        $stmt = $this->pdo->query("SELECT * FROM job_locks WHERE job_name = 'test_job'");
        $this->assertEmpty($stmt->fetchAll());
        
        // Run should be logged as completed
        $stmt = $this->pdo->query("SELECT status FROM job_runs WHERE job_name = 'test_job' ORDER BY id DESC LIMIT 1");
        $this->assertEquals('SUCCESS', $stmt->fetchColumn());
    }

    public function testJobRunnerHandlesExceptionAndAlerts(): void
    {
        $runner = new JobRunner($this->pdo);
        $runner->run('test_exception_job', new TestExceptionJob());
        
        // Lock should be released
        $stmt = $this->pdo->query("SELECT * FROM job_locks WHERE job_name = 'test_exception_job'");
        $this->assertEmpty($stmt->fetchAll());
        
        // Run should be logged as failed
        $stmt = $this->pdo->query("SELECT status, error_summary FROM job_runs WHERE job_name = 'test_exception_job' ORDER BY id DESC LIMIT 1");
        $run = $stmt->fetch();
        $this->assertEquals('FAILED', $run['status']);
        $this->assertStringContainsString('Test crash', $run['error_summary']);
    }

    public function testConcurrencyPreventedIfLockIsActive(): void
    {
        // Simulate active lock
        $this->pdo->exec("INSERT INTO job_locks (job_name, locked_by, locked_at, expires_at) VALUES ('test_concurrent', 'w1', NOW(), DATE_ADD(NOW(), INTERVAL 3600 SECOND))");
        
        $runner = new JobRunner($this->pdo);
        $runner->run('test_concurrent', new TestJob());
        
        // Assert it didn't create a completed run
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM job_runs WHERE job_name = 'test_concurrent' AND status = 'SUCCESS'");
        $this->assertEquals(0, $stmt->fetchColumn());
    }
    
    public function testStaleLockIsRecovered(): void
    {
        // Simulate stale lock (expired)
        $this->pdo->exec("INSERT INTO job_locks (job_name, locked_by, locked_at, expires_at) VALUES ('test_stale', 'w1', DATE_SUB(NOW(), INTERVAL 7200 SECOND), DATE_SUB(NOW(), INTERVAL 3600 SECOND))");
        $this->pdo->exec("INSERT INTO job_runs (job_name, run_id, status, started_at) VALUES ('test_stale', 'run1', 'RUNNING', DATE_SUB(NOW(), INTERVAL 7200 SECOND))");
        
        $runner = new JobRunner($this->pdo);
        $runner->run('test_stale', new TestJob());
        
        // Stale run should be marked failed
        $stmt = $this->pdo->query("SELECT status, error_summary FROM job_runs WHERE job_name = 'test_stale' ORDER BY id ASC LIMIT 1");
        $staleRun = $stmt->fetch();
        $this->assertEquals('FAILED', $staleRun['status']);
        $this->assertStringContainsString('stale', $staleRun['error_summary']);
    }
}
