(function () {
  const ui = window.KickoffUI || {};
  const esc = ui.escapeHtml || function (value) {
    return String(value ?? "").replace(/[&<>"']/g, ch => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" })[ch]);
  };

  const DEFAULT_COVER = "assets/tournament-covers/neon-stadium.svg";
  const formatLabels = {
    "1v1": "1V1 Tournament",
    full_knockout: "Full Knockout",
    group_knockout: "Group Stage + Knockout"
  };
  const formatClasses = {
    "1v1": "tc-format-1v1",
    full_knockout: "tc-format-ko",
    group_knockout: "tc-format-group"
  };

  function attr(value) {
    return esc(value).replace(/`/g, "&#096;");
  }

  function jsArg(value) {
    return String(value ?? "").replace(/\\/g, "\\\\").replace(/'/g, "\\'");
  }

  function number(value) {
    const parsed = Number(value || 0);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function money(amount, currency) {
    const value = number(amount);
    if (value <= 0) return "";
    return `${esc(currency || "TZS")} ${value.toLocaleString("en", { maximumFractionDigits: 0 })}`;
  }

  function coverUrl(t) {
    const raw = String(t.cover_image_url || t.cover_image_path || "").trim();
    const path = raw || DEFAULT_COVER;
    if (/^assets\/tournament-covers\/[-\w./%]+$/i.test(path)) return path;
    if (/^\/?kickoff5\/assets\/tournament-covers\/[-\w./%]+$/i.test(path)) return path;
    if (/^\/assets\/tournament-covers\/[-\w./%]+$/i.test(path)) return path;
    return DEFAULT_COVER;
  }

  function cssCover(t) {
    return coverUrl(t).replace(/['"\\\n\r)]/g, "");
  }

  function detailUrl(t) {
    const id = Math.max(0, Math.trunc(number(t.id)));
    return `tournament_detail.html?id=${id}`;
  }

  function bracketUrl(t) {
    const id = Math.max(0, Math.trunc(number(t.id)));
    return `bracket.html?id=${id}`;
  }

  function dateLabel(t) {
    const value = ["completed", "cancelled"].includes(t.status) && t.completed_at ? t.completed_at : (t.auto_start_at || t.start_date);
    if (!value) return "TBA";
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? "TBA" : date.toLocaleDateString("en", { month: "short", day: "numeric" });
  }

  function dateMetaLabel(t) {
    if (["completed", "cancelled"].includes(String(t.status || ""))) return "Ended";
    return t.auto_start_at ? "Auto Start" : "Starts";
  }

  function platformIcon(platform) {
    const value = String(platform || "").toLowerCase();
    if (value.includes("mobile")) return "fa-mobile-screen-button";
    if (value.includes("pc") || value.includes("desktop")) return "fa-desktop";
    if (value.includes("console") || value.includes("playstation") || value.includes("xbox")) return "fa-gamepad";
    return "fa-layer-group";
  }

  function progress(t) {
    const max = number(t.max_players);
    if (max <= 0) return 0;
    return Math.max(0, Math.min(100, Math.round(number(t.current_players) / max * 100)));
  }

  function statusInfo(t) {
    const status = String(t.status || "open").toLowerCase();
    const full = number(t.max_players) > 0 && number(t.current_players) >= number(t.max_players);
    if (status === "active" && t.format === "group_knockout" && number(t.current_round) <= 1) {
      return { label: "Group Stage", cls: "tc-status-live" };
    }
    if (status === "active" && t.format === "full_knockout") {
      return { label: "Knockout", cls: "tc-status-live" };
    }
    if (status === "active") return { label: "Live", cls: "tc-status-live", dot: true };
    if (status === "open" && full) return { label: "Full", cls: "tc-status-full" };
    if (status === "open") return { label: "Registration Open", cls: "tc-status-open" };
    if (status === "draft") return { label: "Upcoming", cls: "tc-status-upcoming" };
    if (status === "completed") return { label: "Completed", cls: "tc-status-completed" };
    if (status === "cancelled") return { label: "Cancelled", cls: "tc-status-cancelled" };
    if (status === "paused") return { label: "Paused", cls: "tc-status-paused" };
    return { label: status.replace(/_/g, " "), cls: "tc-status-upcoming" };
  }

  function financeInfo(t) {
    const currency = t.currency || "TZS";
    const model = String(t.funding_model || "free_casual");
    if (model === "participant_funded") {
      return {
        prizeLabel: "EST. PRIZE POOL",
        prizeValue: money(t.prize_pool_amount, currency) || esc(t.prize_pool || "TBA"),
        entryValue: money(t.entry_fee_amount, currency) || "TZS 0",
        note: "Participant-Funded Tournament"
      };
    }
    if (model === "kickoff_sponsored") {
      return {
        prizeLabel: "PRIZE POOL",
        prizeValue: money(t.prize_pool_amount || t.kickoff_contribution_amount, currency) || esc(t.prize_pool || "TBA"),
        entryValue: "FREE",
        note: "KICKOFF-Sponsored Tournament"
      };
    }
    return {
      prizeLabel: "PRIZE",
      prizeValue: "NO CASH PRIZE",
      entryValue: "FREE",
      note: "Free Casual Tournament"
    };
  }

  function actionInfo(t, opts) {
    const status = String(t.status || "open");
    const authenticated = Boolean(opts.authenticated || opts.currentUser || window.KickoffCurrentUser);
    const joined = t.joined === true || t.is_joined == 1 || t.viewer_joined == 1;
    const creator = opts.currentUser && Number(opts.currentUser.id) === Number(t.creator_id);
    const full = number(t.max_players) > 0 && number(t.current_players) >= number(t.max_players);
    const paymentStatus = String(t.viewer_payment_status || t.payment_status || "");
    const checkout = String(t.pending_checkout_url || "");

    if (joined && ["pending", "failed"].includes(paymentStatus)) {
      return checkout
        ? { label: "Continue Payment", kind: "pay", href: checkout, loading: "Loading payment..." }
        : { label: "Awaiting Payment", kind: "disabled" };
    }
    if (creator && !["completed", "cancelled"].includes(status)) {
      return { label: "Continue Tournament", kind: "view", href: detailUrl(t), loading: "Opening..." };
    }
    if (joined && t.format === "1v1" && status !== "open") {
      return { label: "View Series", kind: "view", href: detailUrl(t), loading: "Opening..." };
    }
    if (joined && t.format === "full_knockout" && ["active", "completed"].includes(status)) {
      return { label: "View Bracket", kind: "view", href: bracketUrl(t), loading: "Opening bracket..." };
    }
    if (joined) {
      return { label: "View Tournament", kind: "view", href: detailUrl(t), loading: "Opening..." };
    }
    if (!authenticated) {
      return { label: "View Tournament", kind: "view", href: detailUrl(t), loading: "Opening..." };
    }
    if (status !== "open") {
      return { label: "Registration Closed", kind: "disabled" };
    }
    if (full) {
      return { label: "Tournament Full", kind: "disabled" };
    }
    return { label: "Join Tournament", kind: "join", loading: "Checking eligibility..." };
  }

  function metaItem(icon, value, label) {
    return `<div class="tc-meta-item"><i class="fa-solid ${icon}" aria-hidden="true"></i><div><strong>${esc(value)}</strong><span>${esc(label)}</span></div></div>`;
  }

  function renderButton(action, t) {
    const id = Math.max(0, Math.trunc(number(t.id)));
    const href = action.href || detailUrl(t);
    if (action.kind === "disabled") {
      return `<button type="button" class="tc-action" disabled>${esc(action.label)}</button>`;
    }
    return `<button type="button" class="tc-action ${action.kind === "pay" ? "tc-action-pay" : ""}" data-loading-label="${attr(action.loading || "Opening...")}" onclick="KickoffTournamentCard.handleAction(event,this,'${attr(jsArg(action.kind))}',${id},'${attr(jsArg(href))}')">${esc(action.label)}</button>`;
  }

  function render(t, options) {
    const opts = Object.assign({ showActions: true, variant: "full", authenticated: false }, options || {});
    const compact = opts.compact || opts.variant === "compact" || opts.variant === "dashboard";
    const pct = progress(t);
    const status = statusInfo(t);
    const finance = financeInfo(t);
    const action = actionInfo(t, opts);
    const url = detailUrl(t);
    const platform = t.platform_name || t.platform || "Platform";
    const game = t.game_name || t.game || "Game";
    const formatLabel = t.format_label || formatLabels[t.format] || t.format || "Tournament";
    const title = t.name || "Tournament";
    const current = number(t.current_players);
    const max = number(t.max_players);

    return `<article class="tc-card ${compact ? "tc-card-compact" : ""}" style="--tc-cover:url('${attr(cssCover(t))}')">
      <a class="tc-hit" href="${attr(url)}" aria-label="View ${attr(title)}"></a>
      <div class="tc-hero">
        <div class="tc-rails" aria-hidden="true"></div>
        <div class="tc-badge-row">
          <span class="tc-pill ${formatClasses[t.format] || "tc-format-1v1"}">${esc(formatLabel)}</span>
          <span class="tc-pill ${status.cls}">${status.dot ? '<span class="live-dot"></span>' : ""}${esc(status.label)}</span>
        </div>
        <div class="tc-mark" aria-hidden="true"><i class="fa-solid fa-trophy"></i></div>
        <div class="tc-title-wrap">
          <h3 class="tc-title">${esc(title)}</h3>
          <p class="tc-host">Hosted by <strong>${esc(t.creator_username || "KICKOFF")}</strong></p>
        </div>
      </div>
      <div class="tc-content">
        <div class="tc-meta-grid">
          ${metaItem("fa-user-group", max || "TBA", "Players")}
          ${metaItem(platformIcon(platform), platform, "Platform")}
          ${metaItem("fa-gamepad", game, "Game")}
          ${metaItem("fa-calendar", dateLabel(t), dateMetaLabel(t))}
        </div>
        ${compact ? "" : `<div class="tc-finance">
          <div><span>${esc(finance.prizeLabel)}</span><strong>${finance.prizeValue}</strong></div>
          <div><span>ENTRY</span><strong>${finance.entryValue}</strong></div>
        </div>
        <div class="tc-funding">${esc(finance.note)}</div>`}
        <div class="tc-capacity">
          <div class="tc-capacity-top"><span>${current} / ${max || 0} SPOTS FILLED</span><strong>${pct}%</strong></div>
          <div class="tc-progress" aria-hidden="true"><div style="width:${pct}%;"></div></div>
        </div>
        ${opts.showActions ? renderButton(action, t) : ""}
      </div>
    </article>`;
  }

  async function handleAction(event, button, kind, id, href) {
    event.preventDefault();
    event.stopPropagation();
    if (kind === "join" && typeof window.joinTournament === "function") {
      await window.joinTournament(button, id);
      return;
    }
    const open = function () { window.location.href = href || `tournament_detail.html?id=${id}`; };
    if (ui.withButtonLoading && button) {
      await ui.withButtonLoading(button, button.dataset.loadingLabel || "Opening...", async () => open());
    } else {
      open();
    }
  }

  window.KickoffTournamentCard = { render, handleAction, money, progress, statusInfo, financeInfo, coverUrl };
})();
