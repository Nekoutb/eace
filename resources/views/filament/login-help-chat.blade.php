@php($fr = app()->getLocale() === 'fr')

<div id="niu-help" data-url="{{ route('help.ask') }}" data-csrf="{{ csrf_token() }}">
    <style>
        #niu-help{position:fixed;right:1.1rem;bottom:1.1rem;z-index:99999;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}
        #niu-help *{box-sizing:border-box}
        .nh-launch{display:flex;align-items:center;justify-content:center;width:3.4rem;height:3.4rem;border:0;cursor:pointer;
            border-radius:50%;color:#fff;background:linear-gradient(150deg,#15803d,#14532d);box-shadow:0 12px 28px rgba(20,83,45,.4);
            transition:transform .15s}
        .nh-launch:hover{transform:translateY(-2px)}
        .nh-launch svg{width:1.6rem;height:1.6rem}
        .nh-teaser{position:absolute;right:0;bottom:4.1rem;width:14rem;background:#fff;border:1px solid rgba(21,128,61,.18);
            border-radius:14px;padding:.7rem .85rem;font-size:.84rem;color:#0f231a;box-shadow:0 14px 34px rgba(20,83,45,.18);
            opacity:0;transform:translateY(8px);pointer-events:none;transition:opacity .25s,transform .25s}
        .nh-teaser.on{opacity:1;transform:translateY(0)}
        .nh-teaser b{color:#15803d}
        .nh-dot{position:absolute;top:-.2rem;right:-.2rem;width:.95rem;height:.95rem;border-radius:50%;background:#ef4444;
            border:2px solid #fff;display:none}
        .nh-dot.on{display:block}
        .nh-panel{position:absolute;right:0;bottom:4.1rem;width:21rem;max-width:calc(100vw - 2.2rem);background:#fff;border-radius:18px;
            border:1px solid rgba(21,128,61,.16);box-shadow:0 22px 54px rgba(20,83,45,.24);overflow:hidden;display:none;flex-direction:column}
        .nh-panel.on{display:flex}
        .nh-head{background:linear-gradient(150deg,#15803d,#14532d);color:#fff;padding:.95rem 1.1rem}
        .nh-head h3{margin:0;font-size:1rem;font-weight:800}
        .nh-head p{margin:.1rem 0 0;font-size:.78rem;opacity:.9}
        .nh-msgs{padding:.9rem;max-height:16rem;overflow:auto;display:flex;flex-direction:column;gap:.5rem;background:#f8fdfb}
        .nh-b{padding:.55rem .75rem;border-radius:12px;font-size:.86rem;line-height:1.45;max-width:88%;white-space:pre-wrap}
        .nh-b--bot{align-self:flex-start;background:#ecfdf3;border:1px solid #bbf7d0;color:#14532d}
        .nh-b--me{align-self:flex-end;background:#15803d;color:#fff}
        .nh-form{padding:.7rem;border-top:1px solid rgba(21,128,61,.12);display:flex;flex-direction:column;gap:.45rem;background:#fff}
        .nh-form input,.nh-form textarea{width:100%;padding:.55rem .7rem;border:1px solid rgba(21,128,61,.2);border-radius:10px;
            font-size:.86rem;font-family:inherit;outline:none;color:#0f231a}
        .nh-form textarea{resize:none;min-height:2.6rem}
        .nh-form input:focus,.nh-form textarea:focus{border-color:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.2)}
        .nh-send{border:0;cursor:pointer;padding:.55rem;border-radius:10px;font-size:.88rem;font-weight:700;color:#fff;
            background:linear-gradient(150deg,#16a34a,#166534)}
        .nh-send:disabled{opacity:.6;cursor:progress}
        @media (prefers-reduced-motion:reduce){#niu-help *{transition:none!important}}
    </style>

    <div class="nh-teaser" id="nh-teaser">{{ $fr ? '👋 Besoin d’aide pour vous connecter ?' : '👋 Need help signing in?' }} <b>{{ $fr ? 'Discutons.' : 'Let’s chat.' }}</b></div>

    <div class="nh-panel" id="nh-panel">
        <div class="nh-head">
            <h3>{{ $fr ? 'Besoin d’aide ?' : 'Need help?' }}</h3>
            <p>{{ $fr ? 'Notre assistant répond tout de suite.' : 'Our assistant answers right away.' }}</p>
        </div>
        <div class="nh-msgs" id="nh-msgs">
            <div class="nh-b nh-b--bot">{{ $fr ? 'Bonjour ! Posez votre question — connexion, fonctionnement, tarifs…' : 'Hi! Ask anything — signing in, how it works, pricing…' }}</div>
        </div>
        <form class="nh-form" id="nh-form" autocomplete="off">
            <textarea id="nh-text" rows="1" placeholder="{{ $fr ? 'Votre message…' : 'Your message…' }}" maxlength="2000" required></textarea>
            <input type="email" id="nh-email" placeholder="{{ $fr ? 'E-mail (pour une réponse)' : 'Email (so we can reply)' }}" maxlength="255">
            <button type="submit" class="nh-send" id="nh-send">{{ $fr ? 'Envoyer' : 'Send' }}</button>
        </form>
    </div>

    <button type="button" class="nh-launch" id="nh-launch" aria-label="{{ $fr ? 'Ouvrir l’aide' : 'Open help' }}">
        <span class="nh-dot" id="nh-dot"></span>
        @svg('heroicon-o-chat-bubble-left-right')
    </button>
</div>

<script>
(function(){
    var root=document.getElementById('niu-help');if(!root)return;
    var url=root.dataset.url,csrf=root.dataset.csrf;
    var launch=document.getElementById('nh-launch'),panel=document.getElementById('nh-panel'),teaser=document.getElementById('nh-teaser'),
        dot=document.getElementById('nh-dot'),msgs=document.getElementById('nh-msgs'),form=document.getElementById('nh-form'),
        text=document.getElementById('nh-text'),email=document.getElementById('nh-email'),send=document.getElementById('nh-send');
    var opened=false;
    function open(){opened=true;panel.classList.add('on');teaser.classList.remove('on');dot.classList.remove('on');text.focus();}
    function close(){opened=false;panel.classList.remove('on');}
    launch.addEventListener('click',function(){opened?close():open();});
    teaser.addEventListener('click',open);
    // Pop a notification teaser shortly after the page settles.
    setTimeout(function(){if(!opened){teaser.classList.add('on');dot.classList.add('on');}},4500);
    function bubble(t,who){var d=document.createElement('div');d.className='nh-b nh-b--'+who;d.textContent=t;msgs.appendChild(d);msgs.scrollTop=msgs.scrollHeight;return d;}
    form.addEventListener('submit',function(e){
        e.preventDefault();
        var m=text.value.trim();if(!m)return;
        bubble(m,'me');text.value='';send.disabled=true;
        var typing=bubble('…','bot');
        fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
            body:JSON.stringify({message:m,email:email.value.trim()||null})})
        .then(function(r){return r.ok?r.json():Promise.reject(r);})
        .then(function(j){typing.textContent=j.reply;send.disabled=false;})
        .catch(function(){typing.textContent='{{ $fr ? 'Désolé, une erreur est survenue. Réessayez.' : 'Sorry, something went wrong. Please try again.' }}';send.disabled=false;});
    });
})();
</script>
