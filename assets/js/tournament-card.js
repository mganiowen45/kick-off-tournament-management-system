(function () {
  const ui = window.KickoffUI || {};
  const esc = ui.escapeHtml || function (value) {
    return String(value ?? "").replace(/[&<>"']/g, ch => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" })[ch]);
  };

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

  function coverImg(t, className) {
    return window.KickoffCoverImage.render(t, { className, seed: t.name });
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

  const shortFormatLabels = {
    "1v1": "1v1",
    full_knockout: "Knockout",
    group_knockout: "Group + KO"
  };

  function metaItem(icon, value, label) {
    return `<div class="tc-meta-item"><i class="fa-solid ${icon}" aria-hidden="true"></i><div><strong>${esc(value)}</strong><span>${esc(label)}</span></div></div>`;
  }

  function renderButton(action, t, opts) {
    const id = Math.max(0, Math.trunc(number(t.id)));
    const href = action.href || detailUrl(t);
    const cls = opts && opts.className ? opts.className : "tc-action";
    if (action.kind === "disabled") {
      return `<button type="button" class="${cls}" disabled>${esc(action.label)}</button>`;
    }
    return `<button type="button" class="${cls} ${action.kind === "pay" ? "tc-action-pay" : ""}" data-loading-label="${attr(action.loading || "Opening...")}" onclick="KickoffTournamentCard.handleAction(event,this,'${attr(jsArg(action.kind))}',${id},'${attr(jsArg(href))}')">${esc(action.label)}</button>`;
  }

  function renderIconButton(action, t) {
    const id = Math.max(0, Math.trunc(number(t.id)));
    const href = action.href || detailUrl(t);
    if (action.kind === "disabled") {
      return `<button type="button" class="tc-row-btn" disabled aria-label="${attr(action.label)}"><i class="fa-solid fa-lock" aria-hidden="true"></i></button>`;
    }
    return `<button type="button" class="tc-row-btn" data-loading-label="${attr(action.loading || "Opening...")}" aria-label="${attr(action.label)}" onclick="KickoffTournamentCard.handleAction(event,this,'${attr(jsArg(action.kind))}',${id},'${attr(jsArg(href))}')"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>`;
  }

  function rowPrize(finance) {
    if (finance.prizeValue && finance.prizeValue !== "NO CASH PRIZE") {
      return { value: finance.prizeValue, label: "Prize" };
    }
    return { value: finance.entryValue || "FREE", label: "Entry" };
  }

  function renderHero(t, opts) {
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

    return `<article class="tc-card">
      <a class="tc-hit" href="${attr(url)}" aria-label="View ${attr(title)}"></a>
      <div class="tc-hero">
        ${coverImg(t)}
        <div class="tc-hero-scrim" aria-hidden="true"></div>
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
        <div class="tc-finance">
          <div><span>${esc(finance.prizeLabel)}</span><strong>${finance.prizeValue}</strong></div>
          <div><span>ENTRY</span><strong>${finance.entryValue}</strong></div>
        </div>
        <div class="tc-funding">${esc(finance.note)}</div>
        <div class="tc-capacity">
          <div class="tc-capacity-top"><span>${current} / ${max || 0} SPOTS FILLED</span><strong>${pct}%</strong></div>
          <div class="tc-progress" aria-hidden="true"><div style="width:${pct}%;"></div></div>
        </div>
        ${opts.showActions ? renderButton(action, t) : ""}
      </div>
    </article>`;
  }

  function renderBanner(t, opts) {
    const pct = progress(t);
    const status = statusInfo(t);
    const finance = financeInfo(t);
    const action = actionInfo(t, opts);
    const url = detailUrl(t);
    const formatLabel = t.format_label || formatLabels[t.format] || t.format || "Tournament";
    const title = t.name || "Tournament";
    const current = number(t.current_players);
    const max = number(t.max_players);

    return `<article class="tc-card tc-card-banner">
      <a class="tc-hit" href="${attr(url)}" aria-label="View ${attr(title)}"></a>
      <div class="tc-banner-cover">
        ${coverImg(t)}
        <div class="tc-hero-scrim" aria-hidden="true"></div>
        <span class="tc-pill tc-banner-status ${status.cls}">${status.dot ? '<span class="live-dot"></span>' : ""}${esc(status.label)}</span>
      </div>
      <div class="tc-banner-body">
        <div class="tc-banner-format">${esc(formatLabel)}</div>
        <h3 class="tc-banner-title">${esc(title)}</h3>
        <p class="tc-banner-host">Hosted by ${esc(t.creator_username || "KICKOFF")}</p>
        <div class="tc-banner-divider"></div>
        <div class="tc-banner-meta"><span>Players</span><strong>${current} / ${max || 0}</strong></div>
        <div class="tc-progress" aria-hidden="true"><div style="width:${pct}%;"></div></div>
        <div class="tc-banner-foot">
          <div class="tc-banner-prize">${finance.prizeValue && finance.prizeValue !== "NO CASH PRIZE" ? finance.prizeValue : esc(finance.entryValue || "FREE")}</div>
          ${opts.showActions ? renderButton(action, t, { className: "tc-banner-action" }) : ""}
        </div>
      </div>
    </article>`;
  }

  function renderRow(t, opts) {
    const status = statusInfo(t);
    const finance = financeInfo(t);
    const action = actionInfo(t, opts);
    const url = detailUrl(t);
    const shortFormat = shortFormatLabels[t.format] || t.format_label || t.format || "Tournament";
    const title = t.name || "Tournament";
    const current = number(t.current_players);
    const max = number(t.max_players);
    const prize = rowPrize(finance);
    const statusText = status.dot ? "Live now" : `${esc(dateMetaLabel(t))} ${esc(dateLabel(t))}`;

    return `<article class="tc-card tc-card-row">
      <a class="tc-hit" href="${attr(url)}" aria-label="View ${attr(title)}"></a>
      <div class="tc-row-thumb">${coverImg(t)}${status.dot ? '<span class="tc-row-live-dot" aria-hidden="true"></span>' : ""}</div>
      <div class="tc-row-mid">
        <div class="tc-row-title">${esc(title)}</div>
        <div class="tc-row-sub"><span>${esc(shortFormat)}</span><span class="dot" aria-hidden="true"></span><span>${current}/${max || "–"} players</span><span class="dot" aria-hidden="true"></span><span>${statusText}</span></div>
      </div>
      <div class="tc-row-right">
        <div class="tc-row-prize"><strong>${prize.value}</strong><span>${esc(prize.label)}</span></div>
        ${opts.showActions ? renderIconButton(action, t) : ""}
      </div>
    </article>`;
  }

  function render(t, options) {
    const opts = Object.assign({ showActions: true, variant: "hero", authenticated: false }, options || {});
    let variant = opts.variant;
    if (opts.compact || variant === "compact" || variant === "dashboard") variant = "row";
    if (variant === "row") return renderRow(t, opts);
    if (variant === "banner") return renderBanner(t, opts);
    return renderHero(t, opts);
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

  window.KickoffTournamentCard = { render, handleAction, money, progress, statusInfo, financeInfo, coverUrl: t => window.KickoffCoverImage.coverUrl(t) };
})();