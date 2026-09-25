<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;
use App\Services\AlertService;

final class JobRunner
{
    private const LOCK_TTL_SECONDS = 3600; // 1 hour max runtime before considered stale

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function run(string $jobName, callable $callback): void
    {
        $runId = bin2hex(random_bytes(16));
        $workerId = gethostname() . '-' . getmypid() . '-' . $runId;

        // 1. Try to acquire lock
        $acquired = $this->acquireLock($jobName, $workerId);
        
        if (!$acquired) {
            // Lock is held. Is it stale?
            if ($this->recoverStaleLock($jobName)) {
                $acquired = $this->acquireLock($jobName, $workerId);
            }
        }

        if (!$acquired) {
            Logger::info('JobRunner', "Job $jobName is already running, skipping.", ['run_id' => $runId]);
            return;
        }

        // 2. Create job run record
        $this->pdo->prepare(
            "INSERT INTO job_runs (job_name, run_id, status, started_at) VALUES (:name, :run_id, 'RUNNING', NOW())"
        )->execute([':name' => $jobName, ':run_id' => $runId]);

        Logger::info('JobRunner', "Started job $jobName", ['run_id' => $runId]);

        // 3. Run job
        $status = 'SUCCESS';
        $error = null;
        $context = new \stdClass();
        $context->recordsProcessed = 0;
        $context->recordsSucceeded = 0;
        $context->recordsFailed = 0;

        try {
            // Pass context to callback so it can update counts
            $callback($context);
        } catch (Throwable $e) {
            $status = 'FAILED';
            $error = $e->getMessage() . "\n" . $e->getTraceAsString();
            Logger::error('JobRunner', "Job $jobName failed", ['run_id' => $runId, 'error' => $e->getMessage()]);
            
            // Raise operational alert for critical job failure
            (new AlertService($this->pdo))->raise("Job:$jobName", "Job failed: " . $e->getMessage());
        }

        // 4. Update job run record
        $this->pdo->prepare(
            "UPDATE job_runs SET status = :status, finished_at = NOW(), 
                records_processed = :processed, records_succeeded = :succeeded, records_failed = :failed, 
                error_summary = :error WHERE run_id = :run_id"
        )->execute([
            ':status' => $status,
            ':processed' => $context->recordsProcessed,
            ':succeeded' => $context->recordsSucceeded,
            ':failed' => $context->recordsFailed,
            ':error' => $error ? substr($error, 0, 5000) : null,
            ':run_id' => $runId
        ]);

        Logger::info('JobRunner', "Finished job $jobName", ['run_id' => $runId, 'status' => $status]);

        // 5. Release lock
        $this->releaseLock($jobName, $workerId);
    }

    private function acquireLock(string $jobName, string $workerId): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO job_locks (job_name, locked_at, expires_at, locked_by) 
             VALUES (:name, NOW(), DATE_ADD(NOW(), INTERVAL :ttl SECOND), :worker)"
        );
        $stmt->execute([':name' => $jobName, ':ttl' => self::LOCK_TTL_SECONDS, ':worker' => $workerId]);
        
        return $stmt->rowCount() > 0;
    }

    private function releaseLock(string $jobName, string $workerId): void
    {
        $this->pdo->prepare(
            "DELETE FROM job_locks WHERE job_name = :name AND locked_by = :worker"
        )->execute([':name' => $jobName, ':worker' => $workerId]);
    }

    private function recoverStaleLock(string $jobName): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM job_locks WHERE job_name = :name AND expires_at < NOW()"
        );
        $stmt->execute([':name' => $jobName]);
        
        if ($stmt->rowCount() > 0) {
            Logger::warning('JobRunner', "Recovered stale lock for job $jobName");
            (new AlertService($this->pdo))->raise("Job:$jobName", "Job lock became stale and was recovered.");
            
            // Mark previously running runs for this job as failed
            $this->pdo->prepare(
                "UPDATE job_runs SET status = 'FAILED', error_summary = 'Job became stale and lock expired', finished_at = NOW()
                 WHERE job_name = :name AND status = 'RUNNING'"
            )->execute([':name' => $jobName]);
            
            return true;
        }
        return false;
    }
}
