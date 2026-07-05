(() => {

  const style = document.createElement('style');
  style.textContent = `.material-icons-round,.material-icons{display:inline-block;width:1em;height:1em;font-size:24px;line-height:1;vertical-align:-0.125em;flex-shrink:0;background-color:currentColor;mask:var(--local-icon-url) center/contain no-repeat;-webkit-mask:var(--local-icon-url) center/contain no-repeat}.material-icons-round:not([data-local-svg-icon]),.material-icons:not([data-local-svg-icon]){background-color:transparent;width:auto;height:auto}`;
  document.head.appendChild(style);
  const iconMap = {
    add: 'plus', close: 'x', delete: 'x', remove: 'minus', menu: 'menu', search: 'search', home: 'home',
    grid_view: 'layout-grid', dashboard: 'layout-grid', auto_awesome_mosaic: 'layout-grid', category: 'layout-grid',
    shopping_cart: 'shopping-cart', shopping_bag: 'shopping-cart', receipt_long: 'receipt-text', receipt: 'receipt-text',
    notifications: 'bell', priority_high: 'bell', forum: 'bell', support_agent: 'bell',
    person: 'user-round', account_circle: 'user-round', manage_accounts: 'user-round', people: 'user-round',
    logout: 'log-out', inventory_2: 'package', package: 'package', storefront: 'store', store: 'store',
    local_shipping: 'truck', truck: 'truck', favorite: 'heart', favorite_border: 'heart', card_giftcard: 'gift',
    percent: 'badge-percent', verified: 'badge-check', shield: 'shield-check', local_florist: 'leaf',
    spa: 'leaf', eco: 'leaf', camera_alt: 'camera', photo_camera: 'camera', install_mobile: 'strawberry',
    admin_panel_settings: 'shield-check', bar_chart: 'badge-percent', chevron_left: 'minus', chevron_right: 'plus', expand_less: 'minus',
    expand_more: 'plus', check_circle: 'badge-check', done: 'badge-check', task_alt: 'badge-check', done_all: 'badge-check', badge: 'badge-check',
    add_comment: 'plus', add_shopping_cart: 'shopping-cart', arrow_back: 'minus', arrow_forward: 'plus', attach_file: 'receipt-text',
    ac_unit: 'strawberry', bolt: 'badge-percent', calendar_today: 'receipt-text', comment: 'bell', credit_card: 'receipt-text',
    delete_outline: 'x', edit: 'receipt-text', email: 'bell', error: 'bell', flash_on: 'badge-percent', image: 'camera',
    location_on: 'store', lock: 'shield-check', lock_reset: 'shield-check', login: 'user-round', mark_email_read: 'bell',
    more_vert: 'menu', payments: 'receipt-text', person_add: 'user-round', phone: 'bell', schedule: 'bell', sell: 'badge-percent',
    send: 'truck', sms: 'bell', sticky_note_2: 'receipt-text', swap_vert: 'plus', sync: 'truck', telegram: 'bell'
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
