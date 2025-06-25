<!-- Analytics and other script includes -->

<!-- Yandex.Metrika counter -->
<script type="text/javascript">
(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
 m[i].l=1*new Date();
 for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
 k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
(window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

</script>
<script type="text/javascript">
ym(103009404, "init", {
     clickmap:true,
     trackLinks:true,
     accurateTrackBounce:true,
     webvisor:true,
     ecommerce:true
});
window.dataLayer = window.dataLayer || [];
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/103009404" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->

<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.add-to-cart-form').forEach(function (form) {
      form.addEventListener('submit', function () {
        var qty = form.querySelector('input[name="quantity"]').value;
        window.dataLayer.push({
          ecommerce: {
            currencyCode: 'RUB',
            add: {
              products: [{
                id: form.dataset.id,
                name: form.dataset.name,
                price: parseFloat(form.dataset.price),
                quantity: parseFloat(qty)
              }]
            }
          }
        });
      });
    });

    document.querySelectorAll('.remove-from-cart-form').forEach(function (form) {
      form.addEventListener('submit', function () {
        window.dataLayer.push({
          ecommerce: {
            currencyCode: 'RUB',
            remove: {
              products: [{
                id: form.dataset.id,
                name: form.dataset.name,
                price: parseFloat(form.dataset.price),
                quantity: parseFloat(form.dataset.qty)
              }]
            }
          }
        });
      });
    });
  });
</script>
