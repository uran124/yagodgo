(() => {

  const style = document.createElement('style');
  style.textContent = `.material-icons-round,.material-icons{display:inline-block;width:1em;height:1em;font-size:24px;line-height:1;vertical-align:-0.125em;flex-shrink:0;background-color:currentColor;mask:var(--local-icon-url) center/contain no-repeat;-webkit-mask:var(--local-icon-url) center/contain no-repeat}.material-icons-round:not([data-local-svg-icon]),.material-icons:not([data-local-svg-icon]){background-color:transparent;width:auto;height:auto}`;
  document.head.appendChild(style);
  const iconMap = {
    add: 'plus', close: 'x', delete: 'trash', remove: 'minus', menu: 'menu', search: 'search', home: 'home',
    grid_view: 'layout-grid', dashboard: 'dashboard', auto_awesome_mosaic: 'dashboard', category: 'layout-grid',
    shopping_cart: 'shopping-cart', shopping_bag: 'shopping-cart', receipt_long: 'orders', receipt: 'receipt-text',
    notifications: 'bell', priority_high: 'alert-circle', forum: 'message-circle', support_agent: 'messages-square',
    person: 'user-round', account_circle: 'user-round', manage_accounts: 'user-cog', people: 'users',
    logout: 'log-out', inventory_2: 'boxes', package: 'package', storefront: 'store', store: 'store',
    local_shipping: 'truck', truck: 'truck', favorite: 'heart', favorite_border: 'heart', card_giftcard: 'gift',
    percent: 'ticket-percent', verified: 'badge-check', shield: 'shield-check', local_florist: 'leaf',
    spa: 'leaf', eco: 'leaf', camera_alt: 'camera', photo_camera: 'camera', install_mobile: 'app-window',
    admin_panel_settings: 'settings', bar_chart: 'badge-percent', chevron_left: 'chevron-left', chevron_right: 'chevron-right', expand_less: 'chevron-left',
    expand_more: 'chevron-right', check_circle: 'check', done: 'check', task_alt: 'check', done_all: 'check', badge: 'badge-check',
    add_comment: 'message-circle', add_shopping_cart: 'shopping-cart', arrow_back: 'chevron-left', arrow_forward: 'arrow-right', attach_file: 'file-text',
    ac_unit: 'strawberry', article: 'file-text', apps: 'app-window', bolt: 'badge-percent', calendar_today: 'calendar', comment: 'message-circle', credit_card: 'receipt-text',
    delete_outline: 'trash', download: 'download', edit: 'edit', email: 'mail', error: 'alert-circle', flash_on: 'badge-percent', image: 'image',
    local_offer: 'ticket-percent', location_on: 'map', lock: 'lock', lock_reset: 'lock', login: 'user-round', mark_email_read: 'mail',
    more_vert: 'menu', payments: 'receipt-text', person_add: 'user-round', phone: 'phone', schedule: 'clock', sell: 'ticket-percent',
    send: 'send', sms: 'message-circle', sticky_note_2: 'file-text', swap_vert: 'layers', sync: 'upload', telegram: 'send', upload: 'upload'
  };

  const replaceIcon = (node) => {
    if (!(node instanceof HTMLElement) || node.dataset.localSvgIcon === '1') return;
    if (!node.classList.contains('material-icons-round') && !node.classList.contains('material-icons')) return;

    const name = (node.textContent || '').trim();
    const file = iconMap[name];
    if (!file) return;

    node.textContent = '';
    node.dataset.localSvgIcon = '1';
    node.setAttribute('aria-hidden', node.getAttribute('aria-hidden') || 'true');
    node.style.setProperty('--local-icon-url', `url('/assets/icons/${file}.svg')`);
  };

  const replaceIcons = (root = document) => {
    if (root instanceof HTMLElement) replaceIcon(root);
    root.querySelectorAll?.('.material-icons-round, .material-icons').forEach(replaceIcon);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => replaceIcons());
  } else {
    replaceIcons();
  }

  new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        if (node instanceof HTMLElement) replaceIcons(node);
      });
    });
  }).observe(document.documentElement, { childList: true, subtree: true });
})();
