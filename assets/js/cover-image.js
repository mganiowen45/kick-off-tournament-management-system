/**
 * KICKOFF — unified cover image rendering.
 *
 * Replaces the old approach of setting a CSS custom property
 * (--tc-cover) and hoping background-image resolves. Two problems with
 * that: a failed background-image just silently disappears (no error,
 * nothing to catch), and every page that wanted a cover had to
 * duplicate its own "is this URL safe" regex.
 *
 * This module is the ONE place that turns a tournament's
 * cover_image_url into markup, for every page (player or admin) and
 * every card layout (hero / banner / row).
 *
 * The backend (App\Support\CoverImage) already guarantees cover_image_url
 * is either a real file under assets/tournament-covers/ or a self-contained
 * data: URI placeholder - so it should never fail to load. The onerror
 * handler here is a second line of defense only, for the rare case a
 * real image fails at the network layer (e.g. a flaky connection) -
 * it swaps in a client-generated placeholder so the user never sees a
 * broken image icon.
 */
(function () {
  const ui = window.KickoffUI || {};
  const esc = ui.escapeHtml || function (value) {
    return String(value ?? "").replace(/[&<>"']/g, ch => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" })[ch]);
  };

  const PALETTES = [
    ["#0b0f1a", "#152233", "#00d9f5"],
    ["#150b1a", "#231533", "#a78bfa"],
    ["#0b1a12", "#152b1c", "#b5f500"],
    ["#1a0b12", "#2d1726", "#ff4e7a"],
    ["#1a130b", "#2b2015", "#ffb020"],
  ];

  function hashString(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
      hash = (hash * 31 + str.charCodeAt(i)) >>> 0;
    }
    return hash;
  }

  /** Client-side twin of App\Support\CoverImage::placeholderDataUri() — used only as an onerror fallback. */
  function placeholderDataUri(seedText) {
    const seed = (seedText || "").trim() || "KICKOFF";
    const hash = hashString(seed);
    const [bg1, bg2, accent] = PALETTES[hash % PALETTES.length];
    const initial = esc(seed.trim().charAt(0).toUpperCase() || "?");
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 675" role="img" aria-label="Tournament cover">
      <defs>
        <linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="${bg1}"/><stop offset="1" stop-color="${bg2}"/></linearGradient>
        <radialGradient id="r" cx="50%" cy="42%" r="60%"><stop stop-color="${accent}" stop-opacity=".35"/><stop offset="1" stop-color="${accent}" stop-opacity="0"/></radialGradient>
      </defs>
      <rect width="1200" height="675" fill="url(#g)"/>
      <circle cx="600" cy="300" r="320" fill="url(#r)"/>
      <circle cx="600" cy="300" r="130" fill="none" stroke="${accent}" stroke-width="6" opacity=".5"/>
      <text x="600" y="348" font-family="Arial, Helvetica, sans-serif" font-size="160" font-weight="700" fill="${accent}" text-anchor="middle" opacity=".92">${initial}</text>
    </svg>`;
    return "data:image/svg+xml;base64," + btoa(unescape(encodeURIComponent(svg)));
  }

  /** Extracts the best available URL from a tournament-like object. Trusts the backend's resolved value as-is. */
  function coverUrl(t) {
    const value = String((t && (t.cover_image_url || t.cover_image_path)) || "").trim();
    return value || placeholderDataUri(t && (t.name || t.title));
  }

  /**
   * Renders an <img> for a tournament cover.
   * opts.className: extra class(es) to add alongside the base "tc-cover-img".
   * opts.seed: text used to build the onerror fallback placeholder (defaults to t.name).
   */
  function render(t, opts) {
    const options = opts || {};
    const url = coverUrl(t);
    const seed = esc(options.seed || (t && t.name) || "");
    const cls = options.className ? `tc-cover-img ${options.className}` : "tc-cover-img";
    const style = options.style ? ` style="${esc(options.style)}"` : "";
    const fallback = placeholderDataUri(options.seed || (t && t.name));
    return `<img class="${cls}"${style} src="${esc(url)}" alt="" loading="lazy" data-cover-seed="${seed}" `
      + `onerror="this.onerror=null;this.src='${fallback}';this.classList.add('tc-cover-fallback');">`;
  }

  window.KickoffCoverImage = { render, coverUrl, placeholderDataUri };
})();
