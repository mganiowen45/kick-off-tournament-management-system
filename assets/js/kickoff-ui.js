(function () {
  window.KickoffUI = window.KickoffUI || {};
  let csrfToken = null;
  const themes = ["esport", "light", "midnight"];
  const storedTheme = localStorage.getItem("kickoff-theme") || "esport";
  document.documentElement.dataset.theme = themes.includes(storedTheme) ? storedTheme : "esport";

  window.KickoffUI.setCsrfToken = function (token) {
    if (token) csrfToken = token;
  };

  window.KickoffUI.getCsrfToken = function () {
    return csrfToken;
  };

  window.KickoffUI.escapeHtml = window.KickoffUI.escapeHtml || function (value) {
    return String(value ?? "").replace(/[&<>"']/g, function (ch) {
      return ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" })[ch];
    });
  };

  window.KickoffUI.setTheme = function (theme) {
    const selected = themes.includes(theme) ? theme : "esport";
    document.documentElement.dataset.theme = selected;
    localStorage.setItem("kickoff-theme", selected);
  };

  window.KickoffUI.withButtonLoading = async function (button, label, callback) {
    if (!button || button.disabled) return;
    const original = button.innerHTML;
    button.disabled = true;
    button.dataset.loading = "1";
    button.innerHTML = `<span class="spinner" style="width:14px;height:14px;"></span>${window.KickoffUI.escapeHtml(label || "Working...")}`;
    try {
      return await callback();
    } finally {
      button.disabled = false;
      button.dataset.loading = "";
      button.innerHTML = original;
    }
  };

  window.KickoffUI.clearFieldErrors = function (form) {
    const root = form || document;
    root.querySelectorAll(".field-error").forEach(el => el.classList.remove("field-error"));
    root.querySelectorAll(".field-error-msg").forEach(el => {
      el.textContent = "";
      el.classList.remove("visible");
    });
  };

  window.KickoffUI.showFormError = function (form, message, options) {
    const cfg = options || {};
    const root = typeof form === "string" ? document.querySelector(form) : (form || document);
    if (!root) return null;
    const errorId = cfg.errorId || root.getAttribute("data-error-target");
    let errorBox = errorId ? document.getElementById(errorId) : root.querySelector("[data-form-error], .form-error-summary, .alert-error");
    if (!errorBox) {
      errorBox = document.createElement("div");
      root.prepend(errorBox);
    }
    errorBox.className = (errorBox.className || "alert-error") + (errorBox.className.includes("form-error-summary") ? "" : " form-error-summary");
    errorBox.hidden = false;
    errorBox.style.display = "block";
    errorBox.setAttribute("role", "alert");
    errorBox.setAttribute("aria-live", "assertive");
    errorBox.setAttribute("tabindex", "-1");
    const title = cfg.title || "We couldn't save your changes";
    errorBox.innerHTML = `<div class="form-error-summary-title"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>${window.KickoffUI.escapeHtml(title)}</span></div><div>${window.KickoffUI.escapeHtml(message || "Please correct the highlighted fields.")}</div>`;

    window.KickoffUI.clearFieldErrors(root);
    const field = cfg.field ? root.querySelector(cfg.field) || document.getElementById(cfg.field) : null;
    if (field) {
      field.classList.add("field-error");
      const fieldMessage = cfg.fieldMessage || message;
      const described = field.id ? document.getElementById("err-" + field.id) : null;
      if (described) {
        described.textContent = fieldMessage || "";
        described.classList.add("visible");
      }
      field.addEventListener("input", () => field.classList.remove("field-error"), { once: true });
      field.addEventListener("change", () => field.classList.remove("field-error"), { once: true });
    }

    setTimeout(() => {
      try { errorBox.focus({ preventScroll: true }); } catch (error) { errorBox.focus(); }
      errorBox.scrollIntoView({ behavior: "smooth", block: "center" });
      if (field && cfg.focusField !== false) {
        setTimeout(() => field.focus({ preventScroll: true }), 220);
      }
    }, 30);
    return errorBox;
  };

  window.KickoffUI.hideFormError = function (form, options) {
    const cfg = options || {};
    const root = typeof form === "string" ? document.querySelector(form) : (form || document);
    if (!root) return;
    const errorId = cfg.errorId || root.getAttribute("data-error-target");
    const errorBox = errorId ? document.getElementById(errorId) : root.querySelector("[data-form-error], .form-error-summary, .alert-error");
    if (errorBox) {
      errorBox.hidden = true;
      errorBox.style.display = "none";
      errorBox.textContent = "";
    }
    window.KickoffUI.clearFieldErrors(root);
  };

  window.KickoffUI.toast = function (message, type) {
    let stack = document.querySelector(".toast-stack");
    if (!stack) {
      stack = document.createElement("div");
      stack.className = "toast-stack";
      document.body.appendChild(stack);
    }
    const toast = document.createElement("div");
    toast.className = "toast " + (type || "");
    toast.textContent = message || "";
    stack.appendChild(toast);
    setTimeout(() => toast.remove(), 3600);
  };

  window.KickoffUI.confirm = function (options) {
    const cfg = typeof options === "string" ? { title: "Confirm", message: options } : (options || {});
    return new Promise(resolve => {
      const backdrop = document.createElement("div");
      backdrop.className = "modal-backdrop";
      backdrop.innerHTML = `<div class="modal-card" role="dialog" aria-modal="true">
        <h3>${window.KickoffUI.escapeHtml(cfg.title || "Confirm")}</h3>
        <p>${window.KickoffUI.escapeHtml(cfg.message || "Continue?")}</p>
        <div class="modal-actions">
          <button type="button" class="btn-ghost" data-modal-cancel>${window.KickoffUI.escapeHtml(cfg.cancelText || "Cancel")}</button>
          <button type="button" class="${cfg.danger ? "btn-danger" : "btn-lime"}" data-modal-ok>${window.KickoffUI.escapeHtml(cfg.okText || "Continue")}</button>
        </div>
      </div>`;
      document.body.appendChild(backdrop);
      const close = value => { backdrop.remove(); resolve(value); };
      backdrop.querySelector("[data-modal-cancel]").addEventListener("click", () => close(false));
      backdrop.querySelector("[data-modal-ok]").addEventListener("click", () => close(true));
      backdrop.addEventListener("click", event => { if (event.target === backdrop) close(false); });
    });
  };

  window.KickoffUI.prompt = function (options) {
    const cfg = typeof options === "string" ? { title: "Details", message: options } : (options || {});
    return new Promise(resolve => {
      const backdrop = document.createElement("div");
      backdrop.className = "modal-backdrop";
      backdrop.innerHTML = `<div class="modal-card" role="dialog" aria-modal="true">
        <h3>${window.KickoffUI.escapeHtml(cfg.title || "Details")}</h3>
        <p>${window.KickoffUI.escapeHtml(cfg.message || "")}</p>
        <textarea class="form-textarea form-input" data-modal-input maxlength="${Number(cfg.maxLength || 1000)}">${window.KickoffUI.escapeHtml(cfg.value || "")}</textarea>
        <div class="modal-actions">
          <button type="button" class="btn-ghost" data-modal-cancel>${window.KickoffUI.escapeHtml(cfg.cancelText || "Cancel")}</button>
          <button type="button" class="btn-lime" data-modal-ok>${window.KickoffUI.escapeHtml(cfg.okText || "Submit")}</button>
        </div>
      </div>`;
      document.body.appendChild(backdrop);
      const input = backdrop.querySelector("[data-modal-input]");
      input.focus();
      const close = value => { backdrop.remove(); resolve(value); };
      backdrop.querySelector("[data-modal-cancel]").addEventListener("click", () => close(null));
      backdrop.querySelector("[data-modal-ok]").addEventListener("click", () => close(input.value));
      backdrop.addEventListener("click", event => { if (event.target === backdrop) close(null); });
    });
  };

  window.KickoffUI.skeletonRows = window.KickoffUI.skeletonRows || function (count) {
    return Array.from({ length: count || 3 }, function () {
      return '<div class="dash-row"><div style="flex:1"><div class="skeleton" style="width:48%;height:14px;margin-bottom:8px;"></div><div class="skeleton" style="width:70%;height:10px;"></div></div><div class="skeleton" style="width:54px;height:24px;"></div></div>';
    }).join("");
  };

  const originalFetch = window.fetch.bind(window);
  window.fetch = async function (input, init) {
    const requestUrl = typeof input === "string" ? input : input && input.url;
    const target = new URL(requestUrl || window.location.href, window.location.href);
    const options = Object.assign({}, init || {});
    const method = String(options.method || (input && input.method) || "GET").toUpperCase();
    const sameOrigin = target.origin === window.location.origin;
    if (sameOrigin && !["GET", "HEAD", "OPTIONS"].includes(method) && !csrfToken) {
      try {
        const res = await originalFetch("api/users/session.php", { credentials: "same-origin" });
        const data = await res.json();
        if (data && data.csrf_token) csrfToken = data.csrf_token;
      } catch (error) {}
    }
    if (sameOrigin && !["GET", "HEAD", "OPTIONS"].includes(method) && csrfToken) {
      const headers = new Headers(options.headers || {});
      if (!headers.has("X-CSRF-Token")) {
        headers.set("X-CSRF-Token", csrfToken);
      }
      options.headers = headers;
      options.credentials = options.credentials || "same-origin";
    }
    const response = await originalFetch(input, options);
    try {
      const clone = response.clone();
      const contentType = clone.headers.get("content-type") || "";
      if (contentType.includes("application/json")) {
        clone.json().then(data => {
          if (data && data.csrf_token) csrfToken = data.csrf_token;
          if (data && data.user && data.user.theme_preference) window.KickoffUI.setTheme(data.user.theme_preference);
        }).catch(() => {});
      }
    } catch (error) {}
    return response;
  };
})();


window.KickoffUI.shareTournament = async function (id, name) {
  const url = new URL('tournament_detail.html?id=' + encodeURIComponent(id), window.location.href).href;
  const title = name ? 'Join my KICKOFF tournament: ' + name : 'Join my KICKOFF tournament';
  if (navigator.share) {
    try { await navigator.share({ title, text: title, url }); return; } catch (e) {}
  }
  if (navigator.clipboard) {
    await navigator.clipboard.writeText(url);
    window.KickoffUI.toast('Tournament link copied. Share it with players to invite them.', 'success');
  } else {
    await window.KickoffUI.prompt({ title: 'Copy Tournament Link', message: url, value: url, okText: 'Done' });
  }
};
