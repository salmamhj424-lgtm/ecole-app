<?php
// Petites illustrations dessinées en SVG (aucune image à télécharger)

// Visage kawaii : deux yeux + sourire + joues roses, centré en (x, y)
function kawaii(float $x, float $y, float $s = 1): string {
    return sprintf(
        '<g transform="translate(%F %F) scale(%F)"><circle cx="-9" cy="0" r="2.6" fill="#2d2a4a"/><circle cx="9" cy="0" r="2.6" fill="#2d2a4a"/>'
        . '<path d="M-4 5 Q0 9 4 5" fill="none" stroke="#2d2a4a" stroke-width="2" stroke-linecap="round"/>'
        . '<ellipse cx="-15" cy="5" rx="4" ry="2.6" fill="#ff9fc2" opacity=".7"/><ellipse cx="15" cy="5" rx="4" ry="2.6" fill="#ff9fc2" opacity=".7"/></g>',
        $x, $y, $s);
}

// Grande scène : l'école avec soleil, nuages et livres
function illustration_ecole(): string {
    ob_start(); ?>
<svg viewBox="0 0 520 400" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Illustration d'une école">
  <defs>
    <linearGradient id="ciel" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#cfe8ff"/><stop offset="1" stop-color="#fff3fa"/></linearGradient>
    <clipPath id="cadre"><rect x="10" y="10" width="500" height="380" rx="38"/></clipPath>
  </defs>
  <rect x="10" y="10" width="500" height="380" rx="38" fill="url(#ciel)"/>
  <g clip-path="url(#cadre)">
  <!-- soleil -->
  <g class="soleil"><circle class="halo" cx="420" cy="90" r="58" fill="#ffe08a" opacity=".3"/><circle cx="420" cy="90" r="46" fill="#ffe08a"/><?= kawaii(420, 92, 1.1) ?></g>
  <!-- nuages -->
  <g class="nuage n1" fill="#fff"><ellipse cx="110" cy="80" rx="48" ry="22"/><ellipse cx="82" cy="70" rx="26" ry="20"/><ellipse cx="140" cy="68" rx="28" ry="22"/></g>
  <g class="nuage n1"><?= kawaii(110, 84, .8) ?></g>
  <g class="nuage n2" fill="#fff" opacity=".9"><ellipse cx="300" cy="50" rx="34" ry="14"/><ellipse cx="282" cy="44" rx="18" ry="14"/><ellipse cx="318" cy="42" rx="18" ry="14"/></g>
  <!-- colline -->
  <ellipse cx="260" cy="395" rx="330" ry="110" fill="#bfeed4"/>
  <ellipse cx="90" cy="380" rx="150" ry="60" fill="#a8e6c4"/>
  <!-- école -->
  <rect x="150" y="190" width="220" height="150" rx="14" fill="#ffd0e3"/>
  <rect x="150" y="190" width="220" height="150" rx="14" fill="none" stroke="#fff" stroke-width="4"/>
  <polygon points="130,196 260,120 390,196" fill="#9d8cf5"/>
  <polygon points="130,196 260,120 390,196" fill="none" stroke="#fff" stroke-width="4" stroke-linejoin="round"/>
  <circle cx="260" cy="165" r="19" fill="#fff"/><circle cx="260" cy="165" r="19" fill="none" stroke="#7c6cf0" stroke-width="4"/>
  <path d="M260 165 V153 M260 165 L269 170" stroke="#5b4bc4" stroke-width="3" stroke-linecap="round"/>
  <!-- drapeau -->
  <line x1="260" y1="120" x2="260" y2="92" stroke="#5b4bc4" stroke-width="4" stroke-linecap="round"/>
  <polygon class="drapeau" points="260,92 288,100 260,108" fill="#ff6fa5"/>
  <!-- porte et fenêtres -->
  <rect x="235" y="268" width="50" height="72" rx="25 25 0 0" fill="#7c6cf0"/>
  <circle cx="276" cy="306" r="3" fill="#ffe08a"/>
  <g fill="#cfe8ff" stroke="#fff" stroke-width="3"><rect x="172" y="226" width="44" height="40" rx="10"/><rect x="304" y="226" width="44" height="40" rx="10"/>
    <rect x="172" y="284" width="44" height="40" rx="10" opacity=".0"/></g>
  <g stroke="#9d8cf5" stroke-width="2"><line x1="194" y1="226" x2="194" y2="266"/><line x1="172" y1="246" x2="216" y2="246"/><line x1="326" y1="226" x2="326" y2="266"/><line x1="304" y1="246" x2="348" y2="246"/></g>
  <!-- fleurs -->
  <g class="fleurs"><line x1="120" y1="350" x2="120" y2="372" stroke="#5cc79a" stroke-width="3"/><circle cx="120" cy="346" r="9" fill="#ff9fc2"/><circle cx="120" cy="346" r="3.5" fill="#ffe08a"/>
     <line x1="405" y1="352" x2="405" y2="374" stroke="#5cc79a" stroke-width="3"/><circle cx="405" cy="348" r="9" fill="#ffd36e"/><circle cx="405" cy="348" r="3.5" fill="#fff"/></g>
  <!-- livres + crayon -->
  <g class="livres"><g transform="translate(60 318)"><rect width="62" height="14" rx="4" fill="#7c6cf0"/><rect y="-14" width="52" height="14" rx="4" fill="#ff9fc2" transform="translate(5 0)"/><rect y="-28" width="44" height="14" rx="4" fill="#ffd36e" transform="translate(9 0)"/></g></g>
  <g class="crayon"><g transform="translate(410 300) rotate(35)"><rect width="14" height="70" rx="3" fill="#ffd36e"/><polygon points="0,70 14,70 7,86" fill="#ffe9c9"/><polygon points="3.5,78 10.5,78 7,86" fill="#5b4bc4"/><rect width="14" height="10" rx="3" fill="#ff9fc2"/></g></g>
  </g>
  <!-- étoiles -->
  <g class="etoiles" fill="#fff"><path d="M60 150 l4 9 9 4 -9 4 -4 9 -4 -9 -9 -4 9 -4z"/><path d="M470 200 l3 7 7 3 -7 3 -3 7 -3 -7 -7 -3 7 -3z"/><path d="M200 40 l3 7 7 3 -7 3 -3 7 -3 -7 -7 -3 7 -3z"/></g>
</svg>
<?php return ob_get_clean();
}

// Petite scène pour le bandeau d'accueil : cahier, crayon, étoiles
function illustration_bandeau(): string {
    ob_start(); ?>
<svg viewBox="0 0 240 150" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
  <g class="soleil"><circle cx="190" cy="40" r="26" fill="#ffe08a"/><?= kawaii(190, 42, .7) ?></g>
  <g class="nuage n1" fill="#fff"><ellipse cx="60" cy="34" rx="32" ry="14"/><ellipse cx="42" cy="28" rx="16" ry="12"/><ellipse cx="80" cy="27" rx="18" ry="13"/></g>
  <rect x="62" y="70" width="100" height="70" rx="12" fill="#fff"/><rect x="62" y="70" width="14" height="70" rx="6" fill="#7c6cf0"/>
  <g stroke="#e6e0f8" stroke-width="3" stroke-linecap="round"><line x1="88" y1="92" x2="148" y2="92"/><line x1="88" y1="106" x2="140" y2="106"/><line x1="88" y1="120" x2="132" y2="120"/></g>
  <g class="crayon"><g transform="translate(150 96) rotate(40)"><rect width="12" height="60" rx="3" fill="#ffd36e"/><polygon points="0,60 12,60 6,74" fill="#ffe9c9"/><polygon points="3,67 9,67 6,74" fill="#5b4bc4"/><rect width="12" height="9" rx="3" fill="#ff9fc2"/></g></g>
  <g class="etoiles" fill="#fff"><path d="M22 90 l3 7 7 3 -7 3 -3 7 -3 -7 -7 -3 7 -3z"/><path d="M215 100 l3 6 6 3 -6 3 -3 6 -3 -6 -6 -3 6 -3z"/></g>
</svg>
<?php return ob_get_clean();
}

// Sons générés par le navigateur (Web Audio) : aucun fichier audio, bouton 🔊/🔇 pour couper
function script_sons(): string {
    ob_start(); ?>
<button type="button" id="son-toggle" class="son-toggle" aria-label="Activer ou couper le son" title="Son">🔊</button>
<script>
(function () {
  var Ctx = window.AudioContext || window.webkitAudioContext;
  if (!Ctx) { var b0 = document.getElementById('son-toggle'); if (b0) b0.style.display = 'none'; return; }
  var ctx = null, actif = true, attente = null;
  try { actif = localStorage.getItem('son') !== 'off'; } catch (e) {}
  var bouton = document.getElementById('son-toggle');
  function maj() { bouton.textContent = actif ? '🔊' : '🔇'; bouton.classList.toggle('coupe', !actif); }
  function ctxPret() {
    if (!ctx) { try { ctx = new Ctx(); } catch (e) { return null; } }
    if (ctx.state === 'suspended') { ctx.resume(); }
    return ctx;
  }
  // une note : fréquence de départ -> d'arrivée, durée, début, volume, forme d'onde
  function note(c, f1, f2, dur, debut, vol, type) {
    var t = c.currentTime + debut, o = c.createOscillator(), g = c.createGain();
    o.type = type || 'sine';
    o.frequency.setValueAtTime(f1, t);
    if (f2 && f2 !== f1) o.frequency.exponentialRampToValueAtTime(f2, t + dur);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(vol, t + 0.012);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    o.connect(g); g.connect(c.destination);
    o.start(t); o.stop(t + dur + 0.02);
  }
  var SONS = {
    clic:    function (c) { note(c, 520, 780, 0.07, 0, 0.07, 'sine'); },
    succes:  function (c) { note(c, 784, 784, 0.16, 0, 0.09); note(c, 988, 988, 0.16, 0.11, 0.09); note(c, 1318, 1318, 0.30, 0.22, 0.09); },
    erreur:  function (c) { note(c, 220, 160, 0.16, 0, 0.09, 'triangle'); note(c, 200, 140, 0.22, 0.17, 0.09, 'triangle'); },
    bienvenue: function (c) { note(c, 523, 523, 0.14, 0, 0.08); note(c, 659, 659, 0.14, 0.12, 0.08); note(c, 784, 784, 0.14, 0.24, 0.08); note(c, 1047, 1047, 0.40, 0.36, 0.09); }
  };
  function jouer(nom) {
    if (!actif) return;
    var c = ctxPret(); if (!c) return;
    if (c.state === 'running') { SONS[nom](c); }
    else { attente = nom; setTimeout(function () { if (attente === nom) attente = null; }, 5000); } // jouera au premier clic
  }
  function premierGeste() {
    if (attente && actif) { var n = attente; attente = null; var c = ctxPret(); if (c) setTimeout(function () { SONS[n](c); }, 60); }
  }
  document.addEventListener('pointerdown', premierGeste, { once: true });
  document.addEventListener('keydown', premierGeste, { once: true });

  bouton.addEventListener('click', function (e) {
    e.stopPropagation();
    actif = !actif; maj();
    try { localStorage.setItem('son', actif ? 'on' : 'off'); } catch (x) {}
    if (actif) { var c = ctxPret(); if (c) SONS.clic(c); }
  });
  // clic sur un bouton, un lien ou une tuile
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('#son-toggle')) return;
    if (e.target.closest && e.target.closest('a, button, .tuile.lien, summary')) jouer('clic');
  }, true);

  // à l'ouverture de la page : message de succès / d'erreur, ou bienvenue après connexion
  var form = document.querySelector('form.card');
  if (form) { form.addEventListener('submit', function () { try { sessionStorage.setItem('son_bienvenue', '1'); } catch (e) {} }); }
  window.addEventListener('load', function () {
    var bienvenue = false;
    try { bienvenue = sessionStorage.getItem('son_bienvenue') === '1'; sessionStorage.removeItem('son_bienvenue'); } catch (e) {}
    if (document.querySelector('.erreur')) { jouer('erreur'); }
    else if (document.querySelector('.succes')) { jouer('succes'); }
    else if (bienvenue && document.querySelector('.hero')) { jouer('bienvenue'); }
  });
  maj();
})();
</script>
<?php return ob_get_clean();
}
