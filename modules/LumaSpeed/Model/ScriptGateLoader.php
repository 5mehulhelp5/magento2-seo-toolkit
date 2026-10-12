<?php
declare(strict_types=1);

namespace Softcode\LumaSpeed\Model;

/**
 * The inline loader for gated third-party scripts (see HtmlOptimizer::gateScripts). It turns
 * <script type="text/softcode-consent|interaction"> placeholders back into real scripts, in document order:
 *  - "interaction": on the visitor's first pointer, key, touch, wheel or mouse move. Not "scroll": pages scroll
 *    themselves (Magento's checkout does), which would start the scripts without anyone interacting;
 *  - "consent": once the configured consent source reports consent for statistics/marketing.
 * Consent sources: generic (window event "softcode:consent-granted" or window.softcodeConsentGranted(), remembered
 * in localStorage), Magento's cookie restriction mode, Cookiebot, CookieYes, OneTrust. For the vendor sources the
 * vendor's stored choice is what counts, so a withdrawn consent is respected on the next page.
 */
class ScriptGateLoader
{
    public const SOURCES = ['generic', 'magento', 'cookiebot', 'cookieyes', 'onetrust'];

    // Gated snippets often wait for window "load" (most chat/tag embeds do). Started later, that event has passed
    // and they would wait forever, so once gated scripts run, late load/DOMContentLoaded listeners fire at once.
    private const CORE = <<<'JS'
(function(w,d){var done={},patched=0;
function ready(f){d.readyState==='loading'?d.addEventListener('DOMContentLoaded',f):f();}
function late(t,ev,state){var add=t.addEventListener;t.addEventListener=function(type,f,o){if(type===ev&&state()&&typeof f==='function'){setTimeout(function(){f.call(t,new Event(ev));},0);return;}return add.apply(this,arguments);};}
function patch(){if(patched)return;patched=1;late(w,'load',function(){return d.readyState==='complete';});late(d,'DOMContentLoaded',function(){return d.readyState!=='loading';});late(w,'DOMContentLoaded',function(){return d.readyState!=='loading';});}
function run(when){if(done[when])return;done[when]=1;ready(function(){patch();var l=d.querySelectorAll('script[type="text/softcode-'+when+'"]');
for(var i=0;i<l.length;i++){var o=l[i],s=d.createElement('script');for(var j=0;j<o.attributes.length;j++){var a=o.attributes[j];
if(a.name!=='type'&&a.name!=='data-src'&&a.name!=='data-module')s.setAttribute(a.name,a.value);}
if(o.nonce)s.nonce=o.nonce;if(o.getAttribute('data-module'))s.type='module';
if(o.getAttribute('data-src')){s.src=o.getAttribute('data-src');if(!o.hasAttribute('async'))s.async=false;}else{s.text=o.text;}
o.parentNode.replaceChild(s,o);}});}
var ev=['pointerdown','keydown','touchstart','touchmove','wheel','mousemove'];
function interact(){for(var i=0;i<ev.length;i++)w.removeEventListener(ev[i],interact,{passive:true});run('interaction');}
for(var i=0;i<ev.length;i++)w.addEventListener(ev[i],interact,{passive:true});
function consent(){run('consent');}
%SOURCE%
})(window,document);
JS;

    private const SOURCE_JS = [
        'generic' => <<<'JS'
var K='softcode-consent';w.softcodeConsentGranted=function(){try{w.localStorage.setItem(K,'1');}catch(e){}consent();};
w.addEventListener('softcode:consent-granted',w.softcodeConsentGranted);try{if(w.localStorage.getItem(K)==='1')consent();}catch(e){}
JS,
        'magento' => <<<'JS'
function mg(){if(/(^|;\s*)user_allowed_save_cookie=/.test(d.cookie))consent();}mg();
d.addEventListener('click',function(e){if(e.target&&e.target.closest&&e.target.closest('#btn-cookie-allow'))setTimeout(mg,0);},true);
JS,
        'cookiebot' => <<<'JS'
function cb(){var c=w.Cookiebot&&w.Cookiebot.consent;if(c&&(c.statistics||c.marketing))consent();}
w.addEventListener('CookiebotOnAccept',cb);w.addEventListener('CookiebotOnConsentReady',cb);
JS,
        'cookieyes' => <<<'JS'
if(/cookieyes-consent=[^;]*(analytics|advertisement):yes/.test(d.cookie))consent();
d.addEventListener('cookieyes_consent_update',function(e){var a=(e.detail&&e.detail.accepted)||[];if(a.indexOf('analytics')>-1||a.indexOf('advertisement')>-1)consent();});
JS,
        'onetrust' => <<<'JS'
function ot(g){g=','+(g||'')+',';if(g.indexOf(',C0002,')>-1||g.indexOf(',C0004,')>-1)consent();}ot(w.OnetrustActiveGroups);
w.addEventListener('OneTrustGroupsUpdated',function(e){ot([].concat(e.detail||[]).join(','));});
JS,
    ];

    public function build(string $source): string
    {
        $js = self::SOURCE_JS[$source] ?? self::SOURCE_JS['generic'];
        return str_replace(["\r", "\n"], '', str_replace('%SOURCE%', $js, self::CORE));
    }
}
