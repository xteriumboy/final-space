/**
 * Final Space Store — app.js  (08.11 line-complete build)
 * =====================================================================
 * PRO FEATURES (all enabled):
 *  1) License & manifest verification overlay (blocking when invalid)
 *  2) Wallet connect (BNB chain), balance, chain/account listeners
 *  3) Product grid (3-per-row), hover descriptions, neon Buy button
 *  4) Purchase tracking (server + local), wallet-bound
 *  5) Secure Download gating (license + wallet + server URL)
 *  6) "My Purchases" modal (refresh before open, Download buttons)
 *  7) Professional centered toasts (auto-close, friendly errors)
 *  8) Robust server responses (accept ids[] / purchases[] / products[])
 *  9) Backward-compatible download fallback via register_purchase.php?download=
 *
 *  Files expected on server:
 *    - /api/verify_license.php
 *    - /api/fetch_products.php
 *    - /api/get_purchases.php  (preferred format)  OR compatible response
 *    - /api/register_purchase.php (POST register; GET ?download=fallback)
 *    - /api/get_download.php     (preferred, POST wallet+product_id => url)
 *    - /public_config.php        (optional JSON; falls back to <meta> tags)
 *
 *  Notes:
 *    - No UI/UX regressions to your 08.11 build. Download button appears
 *      whenever the wallet owns the product. My Purchases opens a modal.
 *    - Toasts never dump raw JSON/objects; messages are short & friendly.
 *    - Keep ethers.js v5+ loaded on the page before this script.
 * =====================================================================
 */

(function () {
  /* ---------------------------------------------------------
   * Short DOM helpers
   * --------------------------------------------------------- */
  const $  = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  /* ---------------------------------------------------------
   * DOM handles (expected IDs/classes in your HTML)
   * --------------------------------------------------------- */
  const elGrid         = $('#store');                  // product grid container
  const elAddr         = $('#fs-header-addr');         // "Wallet: 0x…"
  const elBal          = $('#fs-header-bal');          // "0.000000 BNB"
  const elBtnConnect   = $('#fs-btn-connect');         // Connect button
  const elBtnDisconnect= $('#fs-btn-disconnect');      // Disconnect button
  const elBtnOwned     = $('#fs-btn-owned');           // "My Purchases" button

  /* ---------------------------------------------------------
   * Global state
   * --------------------------------------------------------- */
  const State = {
    cfg: {
      merchant_address: '',        // BNB recipient
      chain_id_hex: '0x38',        // BSC Mainnet
      chain_id_num: 56,
      currency: 'USDT'
    },
    license: { ok: true, message: '' },
    wallet: null,
    chainId: null,
    balanceBNB: '0.000000',
    provider: null,
    products: [],
    purchases: [],                 // array of product ids (Number)
  };

  /* ---------------------------------------------------------
   * Utility
   * --------------------------------------------------------- */
  const isFn = (v) => typeof v === 'function';
  const short = (a) => a ? `${a.slice(0,6)}...${a.slice(-4)}` : '';
  const clamp = (n, min, max) => Math.max(min, Math.min(max, n));

  function safeMsg(err) {
    if (!err) return 'Unexpected error';
    if (typeof err === 'string') {
      if (/rejected/i.test(err)) return 'Transaction cancelled by user';
      return err.slice(0, 140);
    }
    const code = err.code || err.error?.code;
    const m    = err.message || err.error?.message || '';
    if (code === 'ACTION_REJECTED' || /rejected/i.test(m)) return 'Transaction cancelled by user';
    if (/insufficient funds/i.test(m)) return 'Insufficient funds';
    if (/network|chain/i.test(m)) return 'Wrong network — switch to BSC';
    return (m || 'Operation failed').toString().slice(0, 140);
  }

  function currency(s) {
    const n = Number(s || 0);
    return `$${n.toFixed(2)} ${State.cfg.currency}`;
  }

  /* ---------------------------------------------------------
   * Toasts (professional, centered, auto-close)
   * --------------------------------------------------------- */
  const Toast = (() => {
    let host;
    function ensureHost() {
      if (!host) {
        host = document.createElement('div');
        host.id = 'fs_toasts';
        host.style.cssText = `
          position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
          display:flex;flex-direction:column;align-items:center;gap:12px;
          z-index:9999;pointer-events:none;
        `;
        document.body.appendChild(host);
      }
      return host;
    }
    function color(type) {
      if (type === 'ok')   return '#16a34a';
      if (type === 'warn') return '#f59e0b';
      if (type === 'err')  return '#dc2626';
      return '#2563eb';
    }
    function show(msg, type = 'info', ttl = 3000) {
      const h = ensureHost();
      const el = document.createElement('div');
      el.textContent = msg;
      const bg = color(type);
      el.style.cssText = `
        max-width:min(88vw, 560px);
        text-align:center;
        background:${bg};
        color:#fff;font:500 15px/1.35 Inter,system-ui,sans-serif;
        padding:12px 18px;border-radius:12px;
        box-shadow:0 10px 30px rgba(0,0,0,.45), 0 0 10px ${bg}66;
        opacity:0;transform:scale(.96);transition:opacity .22s, transform .22s;
      `;
      h.appendChild(el);
      requestAnimationFrame(() => { el.style.opacity = '1'; el.style.transform = 'scale(1)'; });
      setTimeout(() => {
        el.style.opacity = '0'; el.style.transform = 'scale(.96)';
        setTimeout(() => el.remove(), 220);
      }, clamp(ttl, 1500, 8000));
    }
    return { show };
  })();

  /* ---------------------------------------------------------
   * License overlay (blocking)
   * --------------------------------------------------------- */
  const LicenseOverlay = (() => {
    let wrap, msgEl;
    function ensure() {
      if (wrap) return wrap;
      wrap = document.createElement('div');
      wrap.id = 'fs_license_overlay';
      wrap.style.cssText = `
        position:fixed;inset:0;background:rgba(0,0,0,.86);
        display:none;align-items:center;justify-content:center;z-index:9998;
        opacity:0;transition:opacity .35s ease;
      `;
      const panel = document.createElement('div');
      panel.style.cssText = `
        width:min(92vw,600px);background:#0b1220;border:1px solid #1e293b;
        border-radius:14px;padding:22px;color:#e5e7eb;
        font-family:Inter,system-ui,sans-serif;box-shadow:0 30px 70px rgba(0,0,0,.55);
      `;
      const title = document.createElement('div');
      title.textContent = 'License Verification Failed';
      title.style.cssText = `font-weight:800;font-size:20px;color:#f87171;margin-bottom:8px`;
      msgEl = document.createElement('div');
      msgEl.style.cssText = `font-size:14px;opacity:.9;line-height:1.6`;
      msgEl.textContent = 'This store cannot run because its license or manifest is invalid.';
      panel.appendChild(title);
      panel.appendChild(msgEl);
      wrap.appendChild(panel);
      document.body.appendChild(wrap);
      return wrap;
    }
    function show(message) {
      const el = ensure();
      if (message) msgEl.textContent = message;
      el.style.display = 'flex';
      requestAnimationFrame(() => { el.style.opacity = '1'; });
    }
    return { show };
  })();

  /* ---------------------------------------------------------
   * Config loading
   * --------------------------------------------------------- */
  async function loadPublicConfig() {
    // Try /public_config.php which should echo JSON
    try {
      const r = await fetch('/public_config.php', { cache: 'no-store' });
      const t = await r.text();
      try {
        const j = JSON.parse(t);
        if (j && typeof j === 'object') {
          State.cfg.merchant_address = (j.merchant_address || State.cfg.merchant_address || '').trim();
          State.cfg.chain_id_hex     = j.chain_id_hex || State.cfg.chain_id_hex;
          State.cfg.chain_id_num     = Number(j.chain_id_num || State.cfg.chain_id_num);
          State.cfg.currency         = (j.currency || State.cfg.currency || 'USDT').toUpperCase();
          return;
        }
      } catch (_) { /* fallthrough to meta */ }
    } catch (_) { /* ignore */ }

    // Fallback to <meta> tags
    const meta = (n) => (document.querySelector(`meta[name="${n}"]`)?.content || '').trim();
    const ma = meta('merchant_address');       if (ma) State.cfg.merchant_address = ma;
    const ch = meta('chain_id_hex');           if (ch) State.cfg.chain_id_hex = ch;
    const cn = Number(meta('chain_id_num'));   if (cn) State.cfg.chain_id_num = cn;
    const cu = meta('currency');               if (cu) State.cfg.currency = cu.toUpperCase();
  }

  /* ---------------------------------------------------------
   * Ethers / Wallet
   * --------------------------------------------------------- */
  function haveEthereum() { return !!window.ethereum; }

  function getProvider() {
    if (!State.provider && haveEthereum()) {
      State.provider = new ethers.providers.Web3Provider(window.ethereum, 'any');
    }
    return State.provider;
  }

  async function ensureBSC() {
    try {
      const p = getProvider(); if (!p) return false;
      const net = await p.getNetwork();
      if (net.chainId === State.cfg.chain_id_num) return true;
      await p.send('wallet_switchEthereumChain', [{ chainId: State.cfg.chain_id_hex }]);
      return true;
    } catch (e) {
      Toast.show('Please switch to Binance Smart Chain.', 'warn', 4200);
      return false;
    }
  }

  async function refreshWallet() {
    const p = getProvider(); if (!p) return;
    const accounts = await p.listAccounts();
    State.wallet = accounts[0] ? accounts[0].toLowerCase() : null;

    const net = await p.getNetwork();
    State.chainId = net.chainId;

    if (State.wallet) {
      try {
        const balWei = await p.getBalance(State.wallet);
        State.balanceBNB = parseFloat(ethers.utils.formatEther(balWei)).toFixed(6);
      } catch {
        State.balanceBNB = '0.000000';
      }
    } else {
      State.balanceBNB = '0.000000';
    }
    paintWalletBar();
  }

  async function connectWallet() {
    if (!haveEthereum()) return Toast.show('MetaMask not detected', 'err');
    try {
      const p = getProvider();
      await p.send('eth_requestAccounts', []);
      await refreshWallet();
      localStorage.setItem('fs_wallet', State.wallet || '');
      Toast.show('Wallet connected', 'ok');
      await refreshPurchases(true);
    } catch (e) {
      Toast.show('Connection cancelled', 'warn');
    }
  }

  function disconnectWallet() {
    State.wallet = null;
    State.balanceBNB = '0.000000';
    localStorage.removeItem('fs_wallet');
    paintWalletBar();
    renderProducts();
    Toast.show('Wallet disconnected', 'ok');
  }

  function paintWalletBar() {
    if (elAddr) elAddr.textContent = State.wallet ? short(State.wallet) : 'Disconnected';
    if (elBal)  elBal.textContent  = State.balanceBNB ? `${State.balanceBNB} BNB` : '';
    if (elBtnConnect)    elBtnConnect.style.display    = State.wallet ? 'none'        : 'inline-flex';
    if (elBtnDisconnect) elBtnDisconnect.style.display = State.wallet ? 'inline-flex' : 'none';
  }

  /* ---------------------------------------------------------
   * API helpers
   * --------------------------------------------------------- */
  async function getJSON(url, opts = {}) {
    const r = await fetch(url, opts);
    const t = await r.text();
    try { return JSON.parse(t); } catch { return { ok:false, error:'bad_json', raw:t }; }
  }

  async function apiVerifyLicense() {
    try {
      const j = await getJSON('/api/verify_license.php', { cache: 'no-store' });
      return j || { ok:false, error:'no_response' };
    } catch { return { ok:false, error:'network' }; }
  }

  async function apiFetchProducts() {
    const j = await getJSON('/api/fetch_products.php', { cache: 'no-store' });
    // If server hints license error, block
    if (j && j.error && /license|manifest/i.test(j.error)) {
      LicenseOverlay.show('License or manifest invalid on the server. Please reseal and try again.');
      return [];
    }
    return (j && j.ok && Array.isArray(j.products)) ? j.products : [];
  }

  async function apiGetPurchases() {
    // Preferred endpoint
    const j = await getJSON('/api/get_purchases.php?wallet=' + encodeURIComponent(State.wallet), { cache: 'no-store' });
    return normalizePurchaseIds(j);
  }

  async function apiRegisterPurchase(pid, txhash) {
    return await getJSON('/api/register_purchase.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        wallet: State.wallet,
        product_id: Number(pid),
        tx: txhash || ''
      })
    });
  }

  async function apiGetDownload(pid) {
    // Preferred
    const a = await getJSON('/api/get_download.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        wallet: State.wallet,
        product_id: Number(pid)
      })
    });
    if (a && a.ok && a.url) return a;

    // Fallback (legacy)
    const b = await getJSON(`/api/register_purchase.php?download=${encodeURIComponent(pid)}&wallet=${encodeURIComponent(State.wallet)}`);
    return b;
  }

  function normalizePurchaseIds(res) {
    if (!res || !res.ok) return [];
    if (Array.isArray(res.ids))        return res.ids.map(n => Number(n));
    if (Array.isArray(res.purchases))  return res.purchases.map(p => Number(p.product_id ?? p.id));
    if (Array.isArray(res.products))   return res.products.map(p => Number(p.id));
    if (Array.isArray(res.list))       return res.list.map(n => Number(n));
    return [];
  }

  /* ---------------------------------------------------------
   * Rendering — products & cards
   * --------------------------------------------------------- */
  function renderProducts() {
    if (!elGrid) return;

    if (!State.products.length) {
      elGrid.innerHTML = `<div style="opacity:.7;text-align:center;padding:36px 8px">No products available.</div>`;
      return;
    }

    elGrid.innerHTML = State.products.map(p => {
      const id    = Number(p.id);
      const img   = p.image || '/assets/img/noimg.jpg';
      const name  = p.name || 'Product';
      const desc  = p.description || '';
      const usd   = Number(p.price || 0).toFixed(2);
      const bnb   = p.price_bnb || '0.000';
      const owned = State.purchases.includes(id);
      const btn   = owned ? 'Download' : `Buy $${usd} ${State.cfg.currency}`;

      return `
        <div class="product-card" data-id="${id}" data-bnb="${bnb}">
          <div class="product-img">
            <img src="${img}" alt="">
            <div class="product-desc">${desc}</div>
          </div>
          <div class="product-info">
            <div class="product-name">${name}</div>
            <div class="product-actions">
              <button class="buy-btn ${owned ? 'download-btn' : ''}" data-id="${id}">${btn}</button>
            </div>
          </div>
        </div>
      `;
    }).join('');

    // Bind buttons
    $$('.buy-btn', elGrid).forEach(btn => {
      btn.addEventListener('click', async (ev) => {
        const node = ev.currentTarget;
        const card = node.closest('.product-card');
        const pid  = Number(card.dataset.id);
        const priceBNB = String(card.dataset.bnb || '0.000');

        if (node.classList.contains('download-btn')) {
          return handleDownload(pid);
        }
        return handleBuy(pid, priceBNB, node);
      });
    });
  }

  /* ---------------------------------------------------------
   * My Purchases modal (refresh before open)
   * --------------------------------------------------------- */
  function closeModalById(id) {
    const el = document.getElementById(id);
    if (el) el.remove();
  }

  function buildModalBase(id, title) {
    closeModalById(id);
    const modal = document.createElement('div');
    modal.id = id;
    modal.style.cssText = `
      position:fixed;inset:0;background:rgba(0,0,0,.85);
      display:flex;align-items:center;justify-content:center;z-index:9999;
      opacity:0;transition:opacity .22s ease;
    `;
    const inner = document.createElement('div');
    inner.style.cssText = `
      background:#0b1220;border:1px solid #1e293b;border-radius:14px;
      color:#e5e7eb;font-family:Inter,system-ui,sans-serif;
      width:min(92vw,640px);max-height:80vh;overflow:auto;padding:18px 18px 16px;
      box-shadow:0 22px 70px rgba(0,0,0,.55);
    `;
    const h = document.createElement('div');
    h.textContent = title;
    h.style.cssText = `font-weight:800;font-size:18px;margin:6px 4px 10px;color:#93c5fd`;
    inner.appendChild(h);
    modal.appendChild(inner);
    document.body.appendChild(modal);
    requestAnimationFrame(()=>{ modal.style.opacity = '1'; });
    return { modal, inner };
  }

  async function openPurchasesModal() {
    await refreshPurchases(false); // sync & repaint grid first

    const { modal, inner } = buildModalBase('fs_purchases_modal', 'My Purchases');

    if (!State.purchases.length) {
      const p = document.createElement('p');
      p.textContent = 'No purchases found.';
      p.style.cssText = 'opacity:.75;margin:10px 4px 2px';
      inner.appendChild(p);
    } else {
      const owned = State.products.filter(p => State.purchases.includes(Number(p.id)));
      owned.forEach(p => {
        const row = document.createElement('div');
        row.style.cssText = `
          display:flex;align-items:center;justify-content:space-between;
          border-bottom:1px solid #152033;padding:10px 6px;
        `;
        const span = document.createElement('span');
        span.textContent = p.name;
        const btn = document.createElement('button');
        btn.textContent = 'Download';
        btn.className = 'fs-btn fs-btn-blue';
        btn.dataset.id = p.id;
        btn.style.cssText = `
          background:#2563eb;border:none;color:#fff;border-radius:10px;
          padding:8px 12px;font-weight:600;cursor:pointer;
        `;
        btn.addEventListener('click', () => handleDownload(p.id));
        row.appendChild(span);
        row.appendChild(btn);
        inner.appendChild(row);
      });
    }

    const close = document.createElement('button');
    close.textContent = 'Close';
    close.className = 'fs-btn';
    close.style.cssText = `
      margin-top:14px;background:#1f2937;border:1px solid #334155;
      color:#cbd5e1;border-radius:10px;padding:8px 12px;font-weight:600;cursor:pointer;
    `;
    close.addEventListener('click', () => modal.remove());
    inner.appendChild(close);
  }

  /* ---------------------------------------------------------
   * Purchase / Download flows
   * --------------------------------------------------------- */
  async function handleBuy(productId, priceBNB, btnNode) {
    if (!haveEthereum()) return Toast.show('MetaMask not installed', 'err');
    if (!State.wallet) { await connectWallet(); if (!State.wallet) return; }
    const okNet = await ensureBSC(); if (!okNet) return;

    if (!State.cfg.merchant_address) {
      return Toast.show('Merchant address missing in config', 'err');
    }

    btnNode.disabled = true;
    try {
      Toast.show('Waiting for wallet confirmation…', 'info', 4200);
      const signer = getProvider().getSigner();
      const tx = await signer.sendTransaction({
        to: State.cfg.merchant_address,
        value: ethers.utils.parseEther(String(priceBNB || '0.000'))
      });

      Toast.show('Transaction sent. Confirming…', 'info', 6000);
      await tx.wait();

      Toast.show('Registering purchase…', 'info', 4000);
      const reg = await apiRegisterPurchase(productId, tx.hash);
      if (!reg.ok) throw new Error(reg.error || 'Failed to register purchase');

      // Local immediate update + server refresh
      if (!State.purchases.includes(Number(productId))) State.purchases.push(Number(productId));
      renderProducts();
      setTimeout(() => refreshPurchases(false), 1000);

      Toast.show('Purchase complete!', 'ok');
    } catch (e) {
      Toast.show(safeMsg(e), 'warn', 5200);
    } finally {
      btnNode.disabled = false;
    }
  }

  async function handleDownload(productId) {
    try {
      const r = await apiGetDownload(productId);
      if (r && r.ok && r.url) {
        const a = document.createElement('a');
        a.href = r.url;
        a.download = '';
        document.body.appendChild(a);
        a.click();
        a.remove();
        Toast.show('Download started', 'ok');
      } else {
        Toast.show((r && r.error) ? String(r.error).slice(0,120) : 'Download unavailable', 'err');
      }
    } catch (e) {
      Toast.show(safeMsg(e), 'err');
    }
  }

  /* ---------------------------------------------------------
   * Purchases refresh + rendering
   * --------------------------------------------------------- */
  async function refreshPurchases(showToast = true) {
    if (!State.wallet) return;
    try {
      const ids = await apiGetPurchases();
      State.purchases = ids;
      renderProducts();
      if (showToast) Toast.show('Purchases refreshed.', 'ok');
    } catch (e) {
      if (showToast) Toast.show('Failed to refresh purchases', 'warn');
    }
  }

  /* ---------------------------------------------------------
   * Boot sequence
   * --------------------------------------------------------- */
  async function boot() {
    // 1) Config
    await loadPublicConfig();

    // 2) License / manifest verification (blocks on failure)
    try {
      const lic = await apiVerifyLicense();
      State.license = lic || { ok:false, message:'No response' };
      if (!State.license.ok) {
        LicenseOverlay.show(State.license.message || 'License verification failed.');
        return; // stop boot
      }
    } catch {
      LicenseOverlay.show('Unable to verify license.');
      return;
    }

    // 3) Products
    State.products = await apiFetchProducts();
    renderProducts();

    // 4) Wallet UI handlers
    if (elBtnConnect)    elBtnConnect.addEventListener('click', connectWallet);
    if (elBtnDisconnect) elBtnDisconnect.addEventListener('click', disconnectWallet);
    if (elBtnOwned)      elBtnOwned.addEventListener('click', openPurchasesModal);

    // 5) Provider listeners
    const p = getProvider();
    if (p && p.provider) {
      p.provider.on('accountsChanged', async () => {
        await refreshWallet();
        await refreshPurchases(false);
      });
      p.provider.on('chainChanged', async () => {
        await refreshWallet();
        await refreshPurchases(false);
      });
    }

    // 6) Restore previous wallet (if any)
    const saved = localStorage.getItem('fs_wallet');
    if (saved && haveEthereum()) {
      try {
        await refreshWallet();
        await refreshPurchases(false);
      } catch { /* ignore */ }
    }
  }

  document.addEventListener('DOMContentLoaded', boot);

  /* ---------------------------------------------------------
   * OPTIONAL: Minimal CSS helpers (in case CSS file misses)
   * (No visual change to your theme; safe defaults only.)
   * --------------------------------------------------------- */
  (function injectMinimalCSS(){
    const id = 'fs_runtime_css_min';
    if (document.getElementById(id)) return;
    const s = document.createElement('style');
    s.id = id;
    s.textContent = `
      .product-card{background:#0b1220;border:1px solid #1e293b;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.35)}
      .product-img{position:relative}
      .product-img img{display:block;width:100%;height:200px;object-fit:cover}
      .product-img .product-desc{position:absolute;inset:auto 0 0 0;background:linear-gradient(180deg,rgba(0,0,0,0) 0, rgba(0,0,0,.55) 45%, rgba(0,0,0,.85) 100%);color:#e5e7eb;padding:12px 14px;font:500 14px Inter,system-ui,sans-serif;opacity:0;transition:opacity .22s}
      .product-img:hover .product-desc{opacity:1}
      .product-info{padding:14px}
      .product-name{font:800 18px Inter,system-ui,sans-serif;color:#fff;margin-bottom:10px}
      .product-actions{display:flex;gap:10px}
      .buy-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;
        width:100%;padding:12px 14px;border:none;border-radius:12px;cursor:pointer;
        font:800 15px Inter,system-ui,sans-serif;color:#fff;background:#2563eb;
        box-shadow:0 8px 24px rgba(37,99,235,.35)}
      .buy-btn:hover{filter:brightness(1.06)}
      .buy-btn.download-btn{background:#16a34a;box-shadow:0 8px 24px rgba(22,163,74,.35)}
      .fs-btn{cursor:pointer}
      .fs-btn-blue{background:#2563eb;color:#fff;border:none;border-radius:10px;padding:8px 12px;font-weight:700}
    `;
    document.head.appendChild(s);
  })();

})();
