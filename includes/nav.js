/**
 * KICKOFF shared shell.
 */
(function () {
  const script = document.currentScript;
  const page = script ? script.dataset.page || "" : "";
  const requestedLayout = script ? script.dataset.layout || "app" : "app";
  const adaptivePages = new Set(["tournaments", "tournament_detail", "bracket", "leaderboard"]);
  const publicPages = new Set(["home", "tournaments", "tournament_detail", "bracket", "leaderboard", "how_it_works", "login", "register"]);
  let session = { loaded: false, logged_in: false, user: null, unread_notifications: 0 };
  let effectiveLayout = requestedLayout === "adaptive" || adaptivePages.has(page) ? "adaptive" : requestedLayout;

  const userLinks = [
    { group: "MAIN", items: [
      ["dashboard", "dashboard.html", "fa-chart-simple", "Dashboard"],
      ["tournaments", "tournaments.html", "fa-compass", "Discover"],
      ["my_tournaments", "my_tournaments.html", "fa-list-check", "My Tournaments"],
      ["matches", "matches.html", "fa-gamepad", "Matches"],
      ["leaderboard", "leaderboard.html", "fa-ranking-star", "Leaderboard"]
    ]},
    { group: "ACCOUNT", items: [
      ["notifications", "notifications.html", "fa-bell", "Notifications", "sidebar-notif-badge"],
      ["payments", "payments.html", "fa-money-bill-transfer", "Payments & Winnings"],
      ["profile", "profile.html", "fa-user-gear", "Profile & Settings"]
    ]}
  ];

  const adminLinks = [
    { group: "MAIN", items: [
      ["admin_dashboard", "admin_dashboard.html", "fa-chart-line", "Dashboard"]
    ]},
    { group: "TOURNAMENTS", items: [
      ["admin_users", "admin_users.html", "fa-users-gear", "Users"],
      ["admin_tournaments", "admin_tournaments.html", "fa-shield-halved", "Tournaments"],
      ["admin_matches", "admin_matches.html", "fa-clipboard-check", "Matches & Results"],
      ["admin_disputes", "admin_disputes.html", "fa-triangle-exclamation", "Disputes", "dispute-badge"],
      ["admin_cancellations", "admin_cancellations.html", "fa-ban", "Cancellation Requests"]
    ]},
    { group: "FINANCE", items: [
      ["admin_transactions", "admin_transactions.html", "fa-receipt", "Payments & Ledger"]
    ]},
    { group: "CONTENT", items: [
      ["admin_tournament_covers", "admin_tournament_covers.html", "fa-image", "Tournament Covers"],
      ["admin_avatars", "admin_avatars.html", "fa-user-astronaut", "Avatars"]
    ]}
  ];

  function active(key) {
    return page === key
      || (page === "tournament_detail" && key === "tournaments")
      || (page === "bracket" && key === "tournaments")
      || (page === "profile_setup" && key === "profile");
  }

  function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, ch => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" })[ch]);
  }

  function badge(id) {
    return id ? `<span id="${id}" class="badge" style="display:none;margin-left:auto;"></span>` : "";
  }

  function shellKind() {
    if (effectiveLayout === "admin") return "admin";
    if (effectiveLayout === "auth") return "auth";
    if (effectiveLayout === "public") return "public";
    if (effectiveLayout === "adaptive") {
      if (!session.logged_in) return "public";
      return session.user && session.user.role === "admin" ? "admin" : "player";
    }
    return "player";
  }

  function accountMarkup(isAdmin) {
    const user = session.user || {};
    const username = user.username || (isAdmin ? "Admin" : "Player");
    const href = isAdmin ? "admin_dashboard.html" : "profile.html";
    return `<div class="sidebar-account">
      <a class="sidebar-account-main" href="${href}">
        <span class="sidebar-account-avatar"><i class="fa-solid ${isAdmin ? "fa-shield-halved" : "fa-user"}"></i></span>
        <span><strong id="sidebar-username">${escapeHtml(username)}</strong><small>${isAdmin ? "Admin" : "Player"}</small></span>
      </a>
      <button class="sidebar-logout" type="button" onclick="kickoffLogout()"><i class="fa-solid fa-right-from-bracket"></i><span>Log Out</span></button>
    </div>`;
  }

  function sidebarMarkup(kind) {
    const isAdmin = kind === "admin";
    const links = isAdmin ? adminLinks : userLinks;
    return `<aside class="sidebar ${isAdmin ? "admin-sidebar" : "user-sidebar"}">
      <div class="logo sidebar-logo">KICK<span>OFF</span></div>
      <div class="sidebar-scroll">
        ${links.map(section => `<div class="sidebar-section">
          <div class="sidebar-label">${section.group}</div>
          ${section.items.map(([key, href, icon, label, badgeId]) => `<a href="${href}" class="sidebar-link${active(key) ? " active" : ""}">
            <span class="icon"><i class="fa-solid ${icon}"></i></span>
            <span>${label}</span>
            ${badge(badgeId)}
          </a>`).join("")}
        </div>`).join("")}
      </div>
      ${accountMarkup(isAdmin)}
    </aside>`;
  }

  function publicTopbarMarkup() {
    const returnSuffix = page === "login" || page === "register" ? location.search : "";
    const loggedIn = session.logged_in && session.user;
    const dashboard = loggedIn && session.user.role === "admin" ? "admin_dashboard.html" : "dashboard.html";
    return `<nav class="site-nav" aria-label="Public navigation">
      <a href="index.html" class="logo">KICK<span>OFF</span></a>
      <button class="public-menu-toggle" type="button" aria-label="Toggle public navigation" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
      <ul class="nav-links" id="public-nav-links">
        <li><a href="index.html"${page === "home" ? " class=\"active\"" : ""}>Home</a></li>
        <li><a href="tournaments.html"${active("tournaments") ? " class=\"active\"" : ""}>Discover Tournaments</a></li>
        <li><a href="leaderboard.html"${page === "leaderboard" ? " class=\"active\"" : ""}>Leaderboard</a></li>
        <li><a href="how_it_works.html"${page === "how_it_works" ? " class=\"active\"" : ""}>How It Works</a></li>
      </ul>
      <div class="nav-right" id="public-nav-right">
        ${loggedIn
          ? `<a href="${dashboard}" class="btn-ghost">Dashboard</a>`
          : `<a href="login.html${returnSuffix}" class="btn-ghost">Log In</a><a href="register.html${returnSuffix}" class="btn-lime">Register</a>`}
      </div>
    </nav>`;
  }

  function appTopbarMarkup(kind) {
    const isAdmin = kind === "admin";
    const mainLinks = isAdmin
      ? [["admin_dashboard", "admin_dashboard.html", "Dashboard"], ["admin_users", "admin_users.html", "Users"], ["admin_tournaments", "admin_tournaments.html", "Tournaments"], ["admin_disputes", "admin_disputes.html", "Disputes"]]
      : [["dashboard", "dashboard.html", "Dashboard"], ["tournaments", "tournaments.html", "Discover"], ["my_tournaments", "my_tournaments.html", "My Tournaments"], ["matches", "matches.html", "Matches"], ["leaderboard", "leaderboard.html", "Leaderboard"]];

    return `<header class="app-topbar ${isAdmin ? "admin-topbar" : "user-topbar"}">
      <div class="topbar-left">
        <button class="menu-toggle" type="button" aria-label="Toggle navigation"><i class="fa-solid fa-bars"></i></button>
        <a href="${isAdmin ? "admin_dashboard.html" : "dashboard.html"}" class="logo">KICK<span>OFF</span></a>
        <span class="topbar-title">${isAdmin ? "Admin" : "Dashboard"}</span>
      </div>
      <div class="topbar-right">
        <ul class="desktop-nav-links">
          ${mainLinks.map(([key, href, label]) => `<li><a href="${href}"${active(key) ? " class=\"active\"" : ""}>${label}</a></li>`).join("")}
        </ul>
        <span class="${isAdmin ? "admin-profile-tag" : "user-profile-tag"}" id="nav-username">${escapeHtml(session.user?.username || (isAdmin ? "Admin" : "Player"))}</span>
        <button class="logout-btn" type="button" onclick="kickoffLogout()">Log Out</button>
      </div>
    </header>`;
  }

  function bottomNavMarkup(kind) {
    if (kind !== "player") return "";
    const items = [
      ["dashboard", "dashboard.html", "fa-house", "Home"],
      ["tournaments", "tournaments.html", "fa-compass", "Discover"],
      ["matches", "matches.html", "fa-gamepad", "Matches"],
      ["my_tournaments", "my_tournaments.html", "fa-list-check", "My"],
      ["profile", "profile.html", "fa-user", "Profile"]
    ];
    return `<nav class="bottom-nav" aria-label="Player mobile navigation">${items.map(([key, href, icon, label]) => `<a href="${href}" class="nav-item${active(key) ? " active" : ""}">
      <i class="fa-solid ${icon}"></i><span>${label}</span>
    </a>`).join("")}</nav>`;
  }

  function footerMarkup(kind) {
    return `<footer><p>Copyright 2026 KICKOFF Tournament Platform</p><p>${kind === "admin" ? "Admin" : "Competitive Play"}</p></footer>`;
  }

  function wrapMain(kind) {
    if (!["admin", "player"].includes(kind)) return;
    const pageMain = document.getElementById("page-main");
    if (!pageMain || pageMain.closest(".app-layout")) return;
    const appLayout = document.createElement("div");
    appLayout.className = `app-layout ${kind === "admin" ? "admin-layout" : "user-layout"}`;
    pageMain.parentNode.insertBefore(appLayout, pageMain);
    appLayout.insertAdjacentHTML("afterbegin", sidebarMarkup(kind));
    const main = document.createElement("main");
    main.className = "main-content";
    appLayout.appendChild(main);
    main.appendChild(pageMain);
  }

  async function fetchSession() {
    try {
      const res = await fetch("api/users/session.php", { credentials: "same-origin" });
      const data = await res.json();
      session = Object.assign({ loaded: true, logged_in: false, user: null, unread_notifications: 0 }, data || {});
    } catch (error) {
      session = { loaded: true, logged_in: false, user: null, unread_notifications: 0 };
    }
  }

  function currentPageReturnTo() {
    return location.pathname.split("/").pop() + location.search;
  }

  function redirectForAuth(kind) {
    if (requestedLayout === "auth") return false;
    if (kind === "public") return false;
    if (!session.logged_in) {
      window.location.href = "login.html?return_to=" + encodeURIComponent(currentPageReturnTo());
      return true;
    }
    if (kind === "admin" && session.user?.role !== "admin") {
      window.location.href = "dashboard.html";
      return true;
    }
    if (kind === "player" && session.user?.role === "admin") {
      window.location.href = "admin_dashboard.html";
      return true;
    }
    if (kind === "player" && Number(session.user?.profile_setup_completed || 0) !== 1 && !["profile_setup", "profile"].includes(page)) {
      window.location.href = "profile_setup.html?return_to=" + encodeURIComponent(currentPageReturnTo());
      return true;
    }
    return false;
  }

  function renderShell() {
    const kind = shellKind();
    if (redirectForAuth(kind)) return;
    document.body.classList.add("app-shell-ready", `${kind}-shell`, kind === "admin" ? "admin-layout" : kind === "player" ? "user-layout" : "auth-layout");
    document.body.insertAdjacentHTML("afterbegin", kind === "public" || kind === "auth" ? publicTopbarMarkup() : appTopbarMarkup(kind));
    wrapMain(kind);
    document.body.insertAdjacentHTML("beforeend", bottomNavMarkup(kind) + footerMarkup(kind));
    bindNavigation(kind);
    updateSessionUI();
  }

  function bindNavigation(kind) {
    const toggle = document.querySelector(".menu-toggle");
    if (toggle) toggle.addEventListener("click", () => document.body.classList.toggle("sidebar-open"));
    const publicToggle = document.querySelector(".public-menu-toggle");
    if (publicToggle) publicToggle.addEventListener("click", () => {
      const open = document.body.classList.toggle("public-nav-open");
      publicToggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
    document.addEventListener("click", event => {
      if (document.body.classList.contains("sidebar-open") && !event.target.closest(".sidebar") && !event.target.closest(".menu-toggle")) {
        document.body.classList.remove("sidebar-open");
      }
      if (document.body.classList.contains("public-nav-open") && !event.target.closest(".site-nav")) {
        document.body.classList.remove("public-nav-open");
        document.querySelector(".public-menu-toggle")?.setAttribute("aria-expanded", "false");
      }
    });
  }

  function updateSessionUI() {
    const username = document.getElementById("nav-username");
    if (username && session.user) username.textContent = session.user.username || username.textContent;
    const sidebarUsername = document.getElementById("sidebar-username");
    if (sidebarUsername && session.user) sidebarUsername.textContent = session.user.username || sidebarUsername.textContent;
    const notificationCount = Number(session.unread_notifications || 0);
    ["nav-notif-badge", "sidebar-notif-badge"].forEach(id => {
      const el = document.getElementById(id);
      if (!el) return;
      el.textContent = notificationCount;
      el.style.display = notificationCount > 0 ? "inline-flex" : "none";
    });
  }

  async function refreshSession() {
    if (requestedLayout === "auth") return;
    await fetchSession();
    updateSessionUI();
  }

  window.KickoffUI = window.KickoffUI || {};
  window.KickoffUI.escapeHtml = escapeHtml;
  window.KickoffUI.skeletonRows = function (count) {
    return Array.from({ length: count || 3 }, () => `<div class="dash-row"><div style="flex:1"><div class="skeleton" style="width:48%;height:14px;margin-bottom:8px;"></div><div class="skeleton" style="width:70%;height:10px;"></div></div><div class="skeleton" style="width:54px;height:24px;"></div></div>`).join("");
  };

  window.kickoffLogout = async function () {
    try { await fetch("api/auth/logout.php", { method: "POST", credentials: "same-origin" }); } catch (error) {}
    window.location.href = "login.html";
  };

  document.addEventListener("DOMContentLoaded", async function () {
    const needsSessionBeforeRender = requestedLayout !== "auth" && (effectiveLayout === "adaptive" || requestedLayout === "app" || requestedLayout === "admin");
    if (needsSessionBeforeRender || requestedLayout === "public") {
      await fetchSession();
    }
    if (effectiveLayout === "adaptive" && !publicPages.has(page)) effectiveLayout = "app";
    renderShell();
    if (requestedLayout !== "auth") window.setInterval(refreshSession, 30000);
  });
})();
