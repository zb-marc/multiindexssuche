(function(){
  function el(tag, attrs){ var n=document.createElement(tag); if(attrs) Object.assign(n, attrs); return n; }
  function $id(id){ return document.getElementById(id); }

  document.addEventListener('DOMContentLoaded', function(){
    var f = $id('asmi-form'),
        q = $id('asmi-q'),
        out = $id('asmi-results');
    if(!f) return;

    f.addEventListener('submit', function(e){
      e.preventDefault();
      var term = (q.value||'').trim();
      if(!term){
        out.innerHTML = '<em>Bitte Suchbegriff eingeben.</em>';
        return;
      }
      out.textContent = 'Suche…';

      fetch(ASMI.endpoint + '?q=' + encodeURIComponent(term), { headers: {'Accept':'application/json'} })
        .then(function(r){ return r.json(); })
        .then(function(data){
          var items = (data && data.results) ? data.results : [];
          if(!items.length){
            out.innerHTML = '<strong>Keine Treffer.</strong>';
            return;
          }
          out.innerHTML = items.map(function(x){
            var badge = x.source==='shopware' ? 'Produkt' : (x.source==='wordpress' ? 'Inhalt' : x.source);
            var price = (x.price!=null && x.price!=='') ? '<div><strong>Preis:</strong> '+x.price+'</div>' : '';
            var img = x.image ? '<img src="'+x.image+'" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:8px;margin-right:.8rem" loading="lazy" />' : '';
            return '<article style="display:flex;gap:.8rem;align-items:flex-start;margin:.6rem 0;padding:.6rem;border:1px solid #e5e7eb;border-radius:10px">'+
              img + '<div><div style="font-size:.75rem;opacity:.7">'+badge+'</div>'+
              '<a href="'+(x.url||'#')+'" style="font-weight:600;text-decoration:none">'+(x.title||'(ohne Titel)')+'</a>'+
              '<p style="margin:.3rem 0 0">'+(x.excerpt||'')+'</p>'+ price + '</div></article>';
          }).join('');
        })
        .catch(function(){
          out.innerHTML = '<strong>Fehler bei der Suche.</strong>';
        });
    });
  });
})();