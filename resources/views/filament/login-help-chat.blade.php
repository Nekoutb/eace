@php($fr = app()->getLocale() === 'fr')

<div id="niu-help" data-url="{{ route('help.ask') }}" data-poll-url="{{ route('help.poll') }}" data-csrf="{{ csrf_token() }}">
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
        .nh-b--agent{align-self:flex-start;background:#fff;border:1px solid #bbf7d0;color:#0f231a;box-shadow:0 2px 6px rgba(20,83,45,.08)}
        .nh-form{padding:.7rem;border-top:1px solid rgba(21,128,61,.12);display:flex;flex-direction:column;gap:.45rem;background:#fff}
        .nh-form input,.nh-form textarea{width:100%;padding:.55rem .7rem;border:1px solid rgba(21,128,61,.2);border-radius:10px;
            font-size:.86rem;font-family:inherit;outline:none;color:#0f231a}
        .nh-form textarea{resize:none;min-height:2.6rem}
        .nh-form input:focus,.nh-form textarea:focus{border-color:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.2)}
        .nh-form input.nh-need{border-color:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.22)}
        .nh-hint{display:flex;align-items:center;gap:.35rem;margin:0;font-size:.7rem;color:#6b8278}
        .nh-hint kbd{background:#ecfdf3;border:1px solid #bbf7d0;border-radius:5px;padding:0 .3rem;font-size:.66rem;font-family:inherit;color:#15803d}
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
            <textarea id="nh-text" rows="2" placeholder="{{ $fr ? 'Votre message…' : 'Your message…' }}" maxlength="2000"></textarea>
            <input type="email" id="nh-email" placeholder="{{ $fr ? 'E-mail (pour une réponse)' : 'Email (so we can reply)' }}" maxlength="255">
            <input type="tel" id="nh-phone" placeholder="{{ $fr ? 'Téléphone / WhatsApp (facultatif)' : 'Phone / WhatsApp (optional)' }}" maxlength="40">
            <p class="nh-hint" id="nh-hint"><kbd>{{ $fr ? 'Entrée' : 'Enter' }}</kbd> {{ $fr ? 'pour envoyer · Maj+Entrée = nouvelle ligne' : 'to send · Shift+Enter for a new line' }}</p>
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
    var url=root.dataset.url,pollUrl=root.dataset.pollUrl,csrf=root.dataset.csrf;
    var launch=document.getElementById('nh-launch'),panel=document.getElementById('nh-panel'),teaser=document.getElementById('nh-teaser'),
        dot=document.getElementById('nh-dot'),msgs=document.getElementById('nh-msgs'),form=document.getElementById('nh-form'),
        text=document.getElementById('nh-text'),email=document.getElementById('nh-email'),phone=document.getElementById('nh-phone'),hint=document.getElementById('nh-hint');
    var opened=false,hintHtml=hint.innerHTML,lastId=0,poller=null;
    function open(){opened=true;panel.classList.add('on');teaser.classList.remove('on');dot.classList.remove('on');text.focus();}
    function close(){opened=false;panel.classList.remove('on');}
    launch.addEventListener('click',function(){opened?close():open();});
    teaser.addEventListener('click',open);
    // Pop a notification teaser shortly after the page settles.
    setTimeout(function(){if(!opened){teaser.classList.add('on');dot.classList.add('on');}},4500);
    function bubble(t,who){var d=document.createElement('div');d.className='nh-b nh-b--'+who;d.textContent=t;msgs.appendChild(d);msgs.scrollTop=msgs.scrollHeight;return d;}
    // Clear the "please add contact" highlight as soon as the visitor starts typing it.
    [email,phone].forEach(function(el){el.addEventListener('input',function(){email.classList.remove('nh-need');phone.classList.remove('nh-need');});});
    // Once escalated, poll for the team's later replies so a waiting visitor sees them live.
    function startPolling(){
        if(poller)return;
        poller=setInterval(function(){
            fetch(pollUrl+'?since='+lastId,{headers:{'Accept':'application/json'},credentials:'same-origin'})
            .then(function(r){return r.ok?r.json():Promise.reject(r);})
            .then(function(j){(j.messages||[]).forEach(function(msg){bubble(msg.body,'agent');if(msg.id>lastId)lastId=msg.id;if(!opened){dot.classList.add('on');}});})
            .catch(function(){});
        },8000);
    }
    // Enter sends; Shift+Enter inserts a new line.
    text.addEventListener('keydown',function(e){
        if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();if(text.value.trim())form.requestSubmit();}
    });
    form.addEventListener('submit',function(e){
        e.preventDefault();
        var m=text.value.trim();if(!m||text.disabled)return;
        bubble(m,'me');text.value='';text.disabled=true;hint.textContent='{{ $fr ? 'Envoi…' : 'Sending…' }}';
        var typing=bubble('…','bot');
        fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},credentials:'same-origin',
            body:JSON.stringify({message:m,email:email.value.trim()||null,phone:phone.value.trim()||null})})
        .then(function(r){return r.ok?r.json():Promise.reject(r);})
        .then(function(j){
            typing.textContent=j.reply;text.disabled=false;hint.innerHTML=hintHtml;
            if(j.needContact){email.classList.add('nh-need');phone.classList.add('nh-need');email.focus();}else{text.focus();}
            if(j.escalated){startPolling();}
        })
        .catch(function(){typing.textContent='{{ $fr ? 'Désolé, une erreur est survenue. Réessayez.' : 'Sorry, something went wrong. Please try again.' }}';text.disabled=false;hint.innerHTML=hintHtml;});
    });
})();
</script>
