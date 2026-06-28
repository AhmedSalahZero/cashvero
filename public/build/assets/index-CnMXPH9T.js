import{B as V,s as k,z as x,M as B,Y as Z,D as N,v as K,d as U,e as y,l as P,J as j,m as z,f as q}from"./sweetalert2.esm.all-B700UZ0f.js";import{a as W,R as Y,O as J,b as Q}from"./index-BwGWCcqs.js";import{s as X}from"./index-DHR97X46.js";import{C as $,x as A}from"./index-BXihAQhl.js";import{c as _,d as D}from"./index-B5nz4CMi.js";import{p as l,d as h,e as M,R as a,y as L,S as ee,l as b,z as v,F as E,f as te,i as ne,U as C,j as S,h as I,t as ie,V as O,u as w,s as g,W as F}from"./runtime-dom.esm-bundler-DWM9hgPS.js";var re=`
    .p-tieredmenu {
        background: dt('tieredmenu.background');
        color: dt('tieredmenu.color');
        border: 1px solid dt('tieredmenu.border.color');
        border-radius: dt('tieredmenu.border.radius');
        min-width: 12.5rem;
    }
    

    .p-tieredmenu-root-list,
    .p-tieredmenu-submenu {
        margin: 0;
        padding: dt('tieredmenu.list.padding');
        list-style: none;
        outline: 0 none;
        display: flex;
        flex-direction: column;
        gap: dt('tieredmenu.list.gap');
    }

    .p-tieredmenu-submenu {
        position: absolute;
        min-width: 100%;
        z-index: 1;
        background: dt('tieredmenu.background');
        color: dt('tieredmenu.color');
        border: 1px solid dt('tieredmenu.border.color');
        border-radius: dt('tieredmenu.border.radius');
        box-shadow: dt('tieredmenu.shadow');
    }

    .p-tieredmenu-item {
        position: relative;
    }

    .p-tieredmenu-item-content {
        transition:
            background dt('tieredmenu.transition.duration'),
            color dt('tieredmenu.transition.duration');
        border-radius: dt('tieredmenu.item.border.radius');
        color: dt('tieredmenu.item.color');
    }

    .p-tieredmenu-item-link {
        cursor: pointer;
        display: flex;
        align-items: center;
        text-decoration: none;
        overflow: hidden;
        position: relative;
        color: inherit;
        padding: dt('tieredmenu.item.padding');
        gap: dt('tieredmenu.item.gap');
        user-select: none;
        outline: 0 none;
    }

    .p-tieredmenu-item-label {
        line-height: 1;
    }

    .p-tieredmenu-item-icon {
        color: dt('tieredmenu.item.icon.color');
    }

    .p-tieredmenu-submenu-icon {
        color: dt('tieredmenu.submenu.icon.color');
        margin-left: auto;
        font-size: dt('tieredmenu.submenu.icon.size');
        width: dt('tieredmenu.submenu.icon.size');
        height: dt('tieredmenu.submenu.icon.size');
    }

    .p-tieredmenu-submenu-icon:dir(rtl) {
        margin-left: 0;
        margin-right: auto;
    }

    .p-tieredmenu-item.p-focus > .p-tieredmenu-item-content {
        color: dt('tieredmenu.item.focus.color');
        background: dt('tieredmenu.item.focus.background');
    }

    .p-tieredmenu-item.p-focus > .p-tieredmenu-item-content .p-tieredmenu-item-icon {
        color: dt('tieredmenu.item.icon.focus.color');
    }

    .p-tieredmenu-item.p-focus > .p-tieredmenu-item-content .p-tieredmenu-submenu-icon {
        color: dt('tieredmenu.submenu.icon.focus.color');
    }

    .p-tieredmenu-item:not(.p-disabled) > .p-tieredmenu-item-content:hover {
        color: dt('tieredmenu.item.focus.color');
        background: dt('tieredmenu.item.focus.background');
    }

    .p-tieredmenu-item:not(.p-disabled) > .p-tieredmenu-item-content:hover .p-tieredmenu-item-icon {
        color: dt('tieredmenu.item.icon.focus.color');
    }

    .p-tieredmenu-item:not(.p-disabled) > .p-tieredmenu-item-content:hover .p-tieredmenu-submenu-icon {
        color: dt('tieredmenu.submenu.icon.focus.color');
    }

    .p-tieredmenu-item-active > .p-tieredmenu-item-content {
        color: dt('tieredmenu.item.active.color');
        background: dt('tieredmenu.item.active.background');
    }

    .p-tieredmenu-item-active > .p-tieredmenu-item-content .p-tieredmenu-item-icon {
        color: dt('tieredmenu.item.icon.active.color');
    }

    .p-tieredmenu-item-active > .p-tieredmenu-item-content .p-tieredmenu-submenu-icon {
        color: dt('tieredmenu.submenu.icon.active.color');
    }

    .p-tieredmenu-separator {
        border-block-start: 1px solid dt('tieredmenu.separator.border.color');
    }

    .p-tieredmenu-overlay {
        box-shadow: dt('tieredmenu.shadow');
        will-change: transform;
    }

    .p-tieredmenu-mobile .p-tieredmenu-submenu {
        position: static;
        box-shadow: none;
        border: 0 none;
        padding-inline-start: dt('tieredmenu.submenu.mobile.indent');
        padding-inline-end: 0;
    }

    .p-tieredmenu-mobile .p-tieredmenu-submenu:dir(rtl) {
        padding-inline-start: 0;
        padding-inline-end: dt('tieredmenu.submenu.mobile.indent');
    }

    .p-tieredmenu-mobile .p-tieredmenu-submenu-icon {
        transition: transform 0.2s;
        transform: rotate(90deg);
    }

    .p-tieredmenu-mobile .p-tieredmenu-item-active > .p-tieredmenu-item-content .p-tieredmenu-submenu-icon {
        transform: rotate(-90deg);
    }
`,se={submenu:function(e){var n=e.instance,r=e.processedItem;return{display:n.isItemActive(r)?"flex":"none"}}},oe={root:function(e){var n=e.props,r=e.instance;return["p-tieredmenu p-component",{"p-tieredmenu-overlay":n.popup,"p-tieredmenu-mobile":r.queryMatches}]},start:"p-tieredmenu-start",rootList:"p-tieredmenu-root-list",item:function(e){var n=e.instance,r=e.processedItem;return["p-tieredmenu-item",{"p-tieredmenu-item-active":n.isItemActive(r),"p-focus":n.isItemFocused(r),"p-disabled":n.isItemDisabled(r)}]},itemContent:"p-tieredmenu-item-content",itemLink:"p-tieredmenu-item-link",itemIcon:"p-tieredmenu-item-icon",itemLabel:"p-tieredmenu-item-label",submenuIcon:"p-tieredmenu-submenu-icon",submenu:"p-tieredmenu-submenu",separator:"p-tieredmenu-separator",end:"p-tieredmenu-end"},ae=V.extend({name:"tieredmenu",style:re,classes:oe,inlineStyles:se}),R={name:"AngleRightIcon",extends:_};function ue(t){return ce(t)||me(t)||le(t)||de()}function de(){throw new TypeError(`Invalid attempt to spread non-iterable instance.
In order to be iterable, non-array objects must have a [Symbol.iterator]() method.`)}function le(t,e){if(t){if(typeof t=="string")return T(t,e);var n={}.toString.call(t).slice(8,-1);return n==="Object"&&t.constructor&&(n=t.constructor.name),n==="Map"||n==="Set"?Array.from(t):n==="Arguments"||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?T(t,e):void 0}}function me(t){if(typeof Symbol<"u"&&t[Symbol.iterator]!=null||t["@@iterator"]!=null)return Array.from(t)}function ce(t){if(Array.isArray(t))return T(t)}function T(t,e){(e==null||e>t.length)&&(e=t.length);for(var n=0,r=Array(e);n<e;n++)r[n]=t[n];return r}function fe(t,e,n,r,o,i){return l(),h("svg",a({width:"14",height:"14",viewBox:"0 0 14 14",fill:"none",xmlns:"http://www.w3.org/2000/svg"},t.pti()),ue(e[0]||(e[0]=[M("path",{d:"M5.25 11.1728C5.14929 11.1694 5.05033 11.1455 4.9592 11.1025C4.86806 11.0595 4.78666 10.9984 4.72 10.9228C4.57955 10.7822 4.50066 10.5916 4.50066 10.3928C4.50066 10.1941 4.57955 10.0035 4.72 9.86283L7.72 6.86283L4.72 3.86283C4.66067 3.71882 4.64765 3.55991 4.68275 3.40816C4.71785 3.25642 4.79932 3.11936 4.91585 3.01602C5.03238 2.91268 5.17819 2.84819 5.33305 2.83149C5.4879 2.81479 5.64411 2.84671 5.78 2.92283L9.28 6.42283C9.42045 6.56346 9.49934 6.75408 9.49934 6.95283C9.49934 7.15158 9.42045 7.34221 9.28 7.48283L5.78 10.9228C5.71333 10.9984 5.63193 11.0595 5.5408 11.1025C5.44966 11.1455 5.35071 11.1694 5.25 11.1728Z",fill:"currentColor"},null,-1)])),16)}R.render=fe;var he={name:"BaseTieredMenu",extends:D,props:{popup:{type:Boolean,default:!1},model:{type:Array,default:null},appendTo:{type:[String,Object],default:"body"},breakpoint:{type:String,default:"960px"},autoZIndex:{type:Boolean,default:!0},baseZIndex:{type:Number,default:0},disabled:{type:Boolean,default:!1},tabindex:{type:Number,default:0},ariaLabelledby:{type:String,default:null},ariaLabel:{type:String,default:null}},style:ae,provide:function(){return{$pcTieredMenu:this,$parentInstance:this}}},G={name:"TieredMenuSub",hostName:"TieredMenu",extends:D,emits:["item-click","item-mouseenter","item-mousemove"],container:null,props:{menuId:{type:String,default:null},focusedItemId:{type:String,default:null},items:{type:Array,default:null},visible:{type:Boolean,default:!1},level:{type:Number,default:0},templates:{type:Object,default:null},activeItemPath:{type:Object,default:null},tabindex:{type:Number,default:0}},methods:{getItemId:function(e){return"".concat(this.menuId,"_").concat(e.key)},getItemKey:function(e){return this.getItemId(e)},getItemProp:function(e,n,r){return e&&e.item?z(e.item[n],r):void 0},getItemLabel:function(e){return this.getItemProp(e,"label")},getItemLabelId:function(e){return"".concat(this.menuId,"_").concat(e.key,"_label")},getPTOptions:function(e,n,r){return this.ptm(r,{context:{item:e.item,index:n,active:this.isItemActive(e),focused:this.isItemFocused(e),disabled:this.isItemDisabled(e)}})},isItemActive:function(e){return this.activeItemPath.some(function(n){return n.key===e.key})},isItemVisible:function(e){return this.getItemProp(e,"visible")!==!1},isItemDisabled:function(e){return this.getItemProp(e,"disabled")},isItemFocused:function(e){return this.focusedItemId===this.getItemId(e)},isItemGroup:function(e){return k(e.items)},onEnter:function(){q(this.container,this.level)},onItemClick:function(e,n){this.getItemProp(n,"command",{originalEvent:e,item:n.item}),this.$emit("item-click",{originalEvent:e,processedItem:n,isFocus:!0})},onItemMouseEnter:function(e,n){this.$emit("item-mouseenter",{originalEvent:e,processedItem:n})},onItemMouseMove:function(e,n){this.$emit("item-mousemove",{originalEvent:e,processedItem:n})},getAriaSetSize:function(){var e=this;return this.items.filter(function(n){return e.isItemVisible(n)&&!e.getItemProp(n,"separator")}).length},getAriaPosInset:function(e){var n=this;return e-this.items.slice(0,e).filter(function(r){return n.isItemVisible(r)&&n.getItemProp(r,"separator")}).length+1},getMenuItemProps:function(e,n){return{action:a({class:this.cx("itemLink"),tabindex:-1},this.getPTOptions(e,n,"itemLink")),icon:a({class:[this.cx("itemIcon"),this.getItemProp(e,"icon")]},this.getPTOptions(e,n,"itemIcon")),label:a({class:this.cx("itemLabel")},this.getPTOptions(e,n,"itemLabel")),submenuicon:a({class:this.cx("submenuIcon")},this.getPTOptions(e,n,"submenuIcon"))}},containerRef:function(e){this.container=e}},components:{AngleRightIcon:R},directives:{ripple:Y}},pe=["tabindex"],Ie=["id","aria-label","aria-disabled","aria-expanded","aria-haspopup","aria-level","aria-setsize","aria-posinset","data-p-active","data-p-focused","data-p-disabled"],be=["onClick","onMouseenter","onMousemove"],ve=["href","target"],ye=["id"],ge=["id"];function ke(t,e,n,r,o,i){var d=L("AngleRightIcon"),p=L("TieredMenuSub",!0),u=ee("ripple");return l(),b(O,a({name:"p-anchored-overlay",onEnter:i.onEnter},t.ptm("menu.transition")),{default:v(function(){return[n.level===0||n.visible?(l(),h("ul",{key:0,ref:i.containerRef,tabindex:n.tabindex},[(l(!0),h(E,null,te(n.items,function(s,m){return l(),h(E,{key:i.getItemKey(s)},[i.isItemVisible(s)&&!i.getItemProp(s,"separator")?(l(),h("li",a({key:0,id:i.getItemId(s),style:i.getItemProp(s,"style"),class:[t.cx("item",{processedItem:s}),i.getItemProp(s,"class")],role:"menuitem","aria-label":i.getItemLabel(s),"aria-disabled":i.isItemDisabled(s)||void 0,"aria-expanded":i.isItemGroup(s)?i.isItemActive(s):void 0,"aria-haspopup":i.isItemGroup(s)&&!i.getItemProp(s,"to")?"menu":void 0,"aria-level":n.level+1,"aria-setsize":i.getAriaSetSize(),"aria-posinset":i.getAriaPosInset(m)},{ref_for:!0},i.getPTOptions(s,m,"item"),{"data-p-active":i.isItemActive(s),"data-p-focused":i.isItemFocused(s),"data-p-disabled":i.isItemDisabled(s)}),[M("div",a({class:t.cx("itemContent"),onClick:function(f){return i.onItemClick(f,s)},onMouseenter:function(f){return i.onItemMouseEnter(f,s)},onMousemove:function(f){return i.onItemMouseMove(f,s)}},{ref_for:!0},i.getPTOptions(s,m,"itemContent")),[n.templates.item?(l(),b(C(n.templates.item),{key:1,item:s.item,hasSubmenu:i.getItemProp(s,"items"),label:i.getItemLabel(s),props:i.getMenuItemProps(s,m)},null,8,["item","hasSubmenu","label","props"])):ne((l(),h("a",a({key:0,href:i.getItemProp(s,"url"),class:t.cx("itemLink"),target:i.getItemProp(s,"target"),tabindex:"-1"},{ref_for:!0},i.getPTOptions(s,m,"itemLink")),[n.templates.itemicon?(l(),b(C(n.templates.itemicon),{key:0,item:s.item,class:S(t.cx("itemIcon"))},null,8,["item","class"])):i.getItemProp(s,"icon")?(l(),h("span",a({key:1,class:[t.cx("itemIcon"),i.getItemProp(s,"icon")]},{ref_for:!0},i.getPTOptions(s,m,"itemIcon")),null,16)):I("",!0),M("span",a({id:i.getItemLabelId(s),class:t.cx("itemLabel")},{ref_for:!0},i.getPTOptions(s,m,"itemLabel")),ie(i.getItemLabel(s)),17,ye),i.getItemProp(s,"items")?(l(),h(E,{key:2},[n.templates.submenuicon?(l(),b(C(n.templates.submenuicon),a({key:0,class:t.cx("submenuIcon"),active:i.isItemActive(s)},{ref_for:!0},i.getPTOptions(s,m,"submenuIcon")),null,16,["class","active"])):(l(),b(d,a({key:1,class:t.cx("submenuIcon")},{ref_for:!0},i.getPTOptions(s,m,"submenuIcon")),null,16,["class"]))],64)):I("",!0)],16,ve)),[[u]])],16,be),i.isItemVisible(s)&&i.isItemGroup(s)?(l(),b(p,a({key:0,id:i.getItemId(s)+"_list",class:t.cx("submenu"),style:t.sx("submenu",!0,{processedItem:s}),"aria-labelledby":i.getItemLabelId(s),role:"menu",menuId:n.menuId,focusedItemId:n.focusedItemId,items:s.items,templates:n.templates,activeItemPath:n.activeItemPath,level:n.level+1,visible:i.isItemActive(s)&&i.isItemGroup(s),pt:t.pt,unstyled:t.unstyled,onItemClick:e[0]||(e[0]=function(c){return t.$emit("item-click",c)}),onItemMouseenter:e[1]||(e[1]=function(c){return t.$emit("item-mouseenter",c)}),onItemMousemove:e[2]||(e[2]=function(c){return t.$emit("item-mousemove",c)})},{ref_for:!0},t.ptm("submenu")),null,16,["id","class","style","aria-labelledby","menuId","focusedItemId","items","templates","activeItemPath","level","visible","pt","unstyled"])):I("",!0)],16,Ie)):I("",!0),i.isItemVisible(s)&&i.getItemProp(s,"separator")?(l(),h("li",a({key:1,id:i.getItemId(s),style:i.getItemProp(s,"style"),class:[t.cx("separator"),i.getItemProp(s,"class")],role:"separator"},{ref_for:!0},t.ptm("separator")),null,16,ge)):I("",!0)],64)}),128))],8,pe)):I("",!0)]}),_:1},16,["onEnter"])}G.render=ke;var H={name:"TieredMenu",extends:he,inheritAttrs:!1,emits:["focus","blur","before-show","before-hide","hide","show"],outsideClickListener:null,matchMediaListener:null,scrollHandler:null,resizeListener:null,target:null,container:null,menubar:null,searchTimeout:null,searchValue:null,data:function(){return{focused:!1,focusedItemInfo:{index:-1,level:0,parentKey:""},activeItemPath:[],visible:!this.popup,submenuVisible:!1,dirty:!1,query:null,queryMatches:!1}},watch:{activeItemPath:function(e){this.popup||(k(e)?(this.bindOutsideClickListener(),this.bindResizeListener()):(this.unbindOutsideClickListener(),this.unbindResizeListener()))}},mounted:function(){this.bindMatchMediaListener()},beforeUnmount:function(){this.unbindOutsideClickListener(),this.unbindResizeListener(),this.unbindMatchMediaListener(),this.scrollHandler&&(this.scrollHandler.destroy(),this.scrollHandler=null),this.container&&this.autoZIndex&&A.clear(this.container),this.target=null,this.container=null},methods:{getItemProp:function(e,n){return e?z(e[n]):void 0},getItemLabel:function(e){return this.getItemProp(e,"label")},isItemDisabled:function(e){return this.getItemProp(e,"disabled")},isItemVisible:function(e){return this.getItemProp(e,"visible")!==!1},isItemGroup:function(e){return k(this.getItemProp(e,"items"))},isItemSeparator:function(e){return this.getItemProp(e,"separator")},getProccessedItemLabel:function(e){return e?this.getItemLabel(e.item):void 0},isProccessedItemGroup:function(e){return e&&k(e.items)},toggle:function(e){this.visible?this.hide(e,!0):this.show(e)},show:function(e,n){this.popup&&(this.$emit("before-show"),this.visible=!0,this.target=this.target||e.currentTarget,this.relatedTarget=e.relatedTarget||null),n&&y(this.menubar)},hide:function(e,n){this.popup&&(this.$emit("before-hide"),this.visible=!1),this.activeItemPath=[],this.focusedItemInfo={index:-1,level:0,parentKey:""},n&&y(this.relatedTarget||this.target||this.menubar),this.dirty=!1},onFocus:function(e){this.focused=!0,this.popup||(this.focusedItemInfo=this.focusedItemInfo.index!==-1?this.focusedItemInfo:{index:this.findFirstFocusedItemIndex(),level:0,parentKey:""}),this.$emit("focus",e)},onBlur:function(e){this.focused=!1,this.focusedItemInfo={index:-1,level:0,parentKey:""},this.searchValue="",this.dirty=!1,this.$emit("blur",e)},onKeyDown:function(e){if(this.disabled){e.preventDefault();return}var n=e.metaKey||e.ctrlKey;switch(e.code){case"ArrowDown":this.onArrowDownKey(e);break;case"ArrowUp":this.onArrowUpKey(e);break;case"ArrowLeft":this.onArrowLeftKey(e);break;case"ArrowRight":this.onArrowRightKey(e);break;case"Home":this.onHomeKey(e);break;case"End":this.onEndKey(e);break;case"Space":this.onSpaceKey(e);break;case"Enter":case"NumpadEnter":this.onEnterKey(e);break;case"Escape":this.onEscapeKey(e);break;case"Tab":this.onTabKey(e);break;case"PageDown":case"PageUp":case"Backspace":case"ShiftLeft":case"ShiftRight":break;default:!n&&j(e.key)&&this.searchItems(e,e.key);break}},onItemChange:function(e,n){var r=e.processedItem,o=e.isFocus;if(!P(r)){var i=r.index,d=r.key,p=r.level,u=r.parentKey,s=r.items,m=k(s),c=this.activeItemPath.filter(function(f){return f.parentKey!==u&&f.parentKey!==d});m&&(c.push(r),this.submenuVisible=!0),this.focusedItemInfo={index:i,level:p,parentKey:u},m&&(this.dirty=!0),o&&y(this.menubar),!(n==="hover"&&this.queryMatches)&&(this.activeItemPath=c)}},onOverlayClick:function(e){J.emit("overlay-click",{originalEvent:e,target:this.target})},onItemClick:function(e){var n=e.originalEvent,r=e.processedItem,o=this.isProccessedItemGroup(r),i=P(r.parent),d=this.isSelected(r);if(d){var p=r.index,u=r.key,s=r.level,m=r.parentKey;this.activeItemPath=this.activeItemPath.filter(function(f){return u!==f.key&&u.startsWith(f.key)}),this.focusedItemInfo={index:p,level:s,parentKey:m},this.dirty=!i,y(this.menubar)}else if(o)this.onItemChange(e);else{var c=i?r:this.activeItemPath.find(function(f){return f.parentKey===""});this.hide(n),this.changeFocusedItemIndex(n,c?c.index:-1),y(this.menubar)}},onItemMouseEnter:function(e){this.dirty&&this.onItemChange(e,"hover")},onItemMouseMove:function(e){this.focused&&this.changeFocusedItemIndex(e,e.processedItem.index)},onArrowDownKey:function(e){var n=this.focusedItemInfo.index!==-1?this.findNextItemIndex(this.focusedItemInfo.index):this.findFirstFocusedItemIndex();this.changeFocusedItemIndex(e,n),e.preventDefault()},onArrowUpKey:function(e){if(e.altKey){if(this.focusedItemInfo.index!==-1){var n=this.visibleItems[this.focusedItemInfo.index],r=this.isProccessedItemGroup(n);!r&&this.onItemChange({originalEvent:e,processedItem:n})}this.popup&&this.hide(e,!0),e.preventDefault()}else{var o=this.focusedItemInfo.index!==-1?this.findPrevItemIndex(this.focusedItemInfo.index):this.findLastFocusedItemIndex();this.changeFocusedItemIndex(e,o),e.preventDefault()}},onArrowLeftKey:function(e){var n=this,r=this.visibleItems[this.focusedItemInfo.index],o=this.activeItemPath.find(function(d){return d.key===r.parentKey}),i=P(r.parent);i||(this.focusedItemInfo={index:-1,parentKey:o?o.parentKey:""},this.searchValue="",this.onArrowDownKey(e)),this.activeItemPath=this.activeItemPath.filter(function(d){return d.parentKey!==n.focusedItemInfo.parentKey}),e.preventDefault()},onArrowRightKey:function(e){var n=this.visibleItems[this.focusedItemInfo.index],r=this.isProccessedItemGroup(n);r&&(this.onItemChange({originalEvent:e,processedItem:n}),this.focusedItemInfo={index:-1,parentKey:n.key},this.searchValue="",this.onArrowDownKey(e)),e.preventDefault()},onHomeKey:function(e){this.changeFocusedItemIndex(e,this.findFirstItemIndex()),e.preventDefault()},onEndKey:function(e){this.changeFocusedItemIndex(e,this.findLastItemIndex()),e.preventDefault()},onEnterKey:function(e){if(this.focusedItemInfo.index!==-1){var n=x(this.menubar,'li[id="'.concat("".concat(this.focusedItemId),'"]')),r=n&&x(n,'[data-pc-section="itemlink"]');if(r?r.click():n&&n.click(),!this.popup){var o=this.visibleItems[this.focusedItemInfo.index],i=this.isProccessedItemGroup(o);!i&&(this.focusedItemInfo.index=this.findFirstFocusedItemIndex())}}e.preventDefault()},onSpaceKey:function(e){this.onEnterKey(e)},onEscapeKey:function(e){if(this.popup||this.focusedItemInfo.level!==0){var n=this.focusedItemInfo;this.hide(e,!1),this.focusedItemInfo={index:Number(n.parentKey.split("_")[0]),level:0,parentKey:""},this.popup&&y(this.target)}e.preventDefault()},onTabKey:function(e){if(this.focusedItemInfo.index!==-1){var n=this.visibleItems[this.focusedItemInfo.index],r=this.isProccessedItemGroup(n);!r&&this.onItemChange({originalEvent:e,processedItem:n})}this.hide()},onEnter:function(e){this.autoZIndex&&A.set("menu",e,this.baseZIndex+this.$primevue.config.zIndex.menu),U(e,{position:"absolute",top:"0"}),this.alignOverlay(),y(this.menubar),this.scrollInView()},onAfterEnter:function(){this.bindOutsideClickListener(),this.bindScrollListener(),this.bindResizeListener(),this.$emit("show")},onLeave:function(){this.unbindOutsideClickListener(),this.unbindScrollListener(),this.unbindResizeListener(),this.$emit("hide"),this.container=null,this.dirty=!1},onAfterLeave:function(e){this.autoZIndex&&A.clear(e)},alignOverlay:function(){N(this.container,this.target);var e=K(this.target);e>K(this.container)&&(this.container.style.minWidth=K(this.target)+"px")},bindOutsideClickListener:function(){var e=this;this.outsideClickListener||(this.outsideClickListener=function(n){var r=e.container&&!e.container.contains(n.target),o=e.popup?!(e.target&&(e.target===n.target||e.target.contains(n.target))):!0;r&&o&&e.hide()},document.addEventListener("click",this.outsideClickListener,!0))},unbindOutsideClickListener:function(){this.outsideClickListener&&(document.removeEventListener("click",this.outsideClickListener,!0),this.outsideClickListener=null)},bindScrollListener:function(){var e=this;this.scrollHandler||(this.scrollHandler=new $(this.target,function(n){e.hide(n,!0)})),this.scrollHandler.bindScrollListener()},unbindScrollListener:function(){this.scrollHandler&&this.scrollHandler.unbindScrollListener()},bindResizeListener:function(){var e=this;this.resizeListener||(this.resizeListener=function(n){Z()||e.hide(n,!0)},window.addEventListener("resize",this.resizeListener))},unbindResizeListener:function(){this.resizeListener&&(window.removeEventListener("resize",this.resizeListener),this.resizeListener=null)},bindMatchMediaListener:function(){var e=this;if(!this.matchMediaListener){var n=matchMedia("(max-width: ".concat(this.breakpoint,")"));this.query=n,this.queryMatches=n.matches,this.matchMediaListener=function(){e.queryMatches=n.matches},this.query.addEventListener("change",this.matchMediaListener)}},unbindMatchMediaListener:function(){this.matchMediaListener&&(this.query.removeEventListener("change",this.matchMediaListener),this.matchMediaListener=null)},isItemMatched:function(e){var n;return this.isValidItem(e)&&((n=this.getProccessedItemLabel(e))===null||n===void 0?void 0:n.toLocaleLowerCase().startsWith(this.searchValue.toLocaleLowerCase()))},isValidItem:function(e){return!!e&&!this.isItemDisabled(e.item)&&!this.isItemSeparator(e.item)&&this.isItemVisible(e.item)},isValidSelectedItem:function(e){return this.isValidItem(e)&&this.isSelected(e)},isSelected:function(e){return this.activeItemPath.some(function(n){return n.key===e.key})},findFirstItemIndex:function(){var e=this;return this.visibleItems.findIndex(function(n){return e.isValidItem(n)})},findLastItemIndex:function(){var e=this;return B(this.visibleItems,function(n){return e.isValidItem(n)})},findNextItemIndex:function(e){var n=this,r=e<this.visibleItems.length-1?this.visibleItems.slice(e+1).findIndex(function(o){return n.isValidItem(o)}):-1;return r>-1?r+e+1:e},findPrevItemIndex:function(e){var n=this,r=e>0?B(this.visibleItems.slice(0,e),function(o){return n.isValidItem(o)}):-1;return r>-1?r:e},findSelectedItemIndex:function(){var e=this;return this.visibleItems.findIndex(function(n){return e.isValidSelectedItem(n)})},findFirstFocusedItemIndex:function(){var e=this.findSelectedItemIndex();return e<0?this.findFirstItemIndex():e},findLastFocusedItemIndex:function(){var e=this.findSelectedItemIndex();return e<0?this.findLastItemIndex():e},searchItems:function(e,n){var r=this;this.searchValue=(this.searchValue||"")+n;var o=-1,i=!1;return this.focusedItemInfo.index!==-1?(o=this.visibleItems.slice(this.focusedItemInfo.index).findIndex(function(d){return r.isItemMatched(d)}),o=o===-1?this.visibleItems.slice(0,this.focusedItemInfo.index).findIndex(function(d){return r.isItemMatched(d)}):o+this.focusedItemInfo.index):o=this.visibleItems.findIndex(function(d){return r.isItemMatched(d)}),o!==-1&&(i=!0),o===-1&&this.focusedItemInfo.index===-1&&(o=this.findFirstFocusedItemIndex()),o!==-1&&this.changeFocusedItemIndex(e,o),this.searchTimeout&&clearTimeout(this.searchTimeout),this.searchTimeout=setTimeout(function(){r.searchValue="",r.searchTimeout=null},500),i},changeFocusedItemIndex:function(e,n){this.focusedItemInfo.index!==n&&(this.focusedItemInfo.index=n,this.scrollInView())},scrollInView:function(){var e=arguments.length>0&&arguments[0]!==void 0?arguments[0]:-1,n=e!==-1?"".concat(this.$id,"_").concat(e):this.focusedItemId,r=x(this.menubar,'li[id="'.concat(n,'"]'));r&&r.scrollIntoView&&r.scrollIntoView({block:"nearest",inline:"start"})},createProcessedItems:function(e){var n=this,r=arguments.length>1&&arguments[1]!==void 0?arguments[1]:0,o=arguments.length>2&&arguments[2]!==void 0?arguments[2]:{},i=arguments.length>3&&arguments[3]!==void 0?arguments[3]:"",d=[];return e&&e.forEach(function(p,u){var s=(i!==""?i+"_":"")+u,m={item:p,index:u,level:r,key:s,parent:o,parentKey:i};m.items=n.createProcessedItems(p.items,r+1,m,s),d.push(m)}),d},containerRef:function(e){this.container=e},menubarRef:function(e){this.menubar=e?e.$el:void 0}},computed:{processedItems:function(){return this.createProcessedItems(this.model||[])},visibleItems:function(){var e=this,n=this.activeItemPath.find(function(r){return r.key===e.focusedItemInfo.parentKey});return n?n.items:this.processedItems},focusedItemId:function(){return this.focusedItemInfo.index!==-1?"".concat(this.$id).concat(k(this.focusedItemInfo.parentKey)?"_"+this.focusedItemInfo.parentKey:"","_").concat(this.focusedItemInfo.index):null}},components:{TieredMenuSub:G,Portal:W}},Le=["id"];function we(t,e,n,r,o,i){var d=L("TieredMenuSub"),p=L("Portal");return l(),b(p,{appendTo:t.appendTo,disabled:!t.popup},{default:v(function(){return[w(O,a({name:"p-anchored-overlay",onEnter:i.onEnter,onAfterEnter:i.onAfterEnter,onLeave:i.onLeave,onAfterLeave:i.onAfterLeave},t.ptm("transition")),{default:v(function(){return[o.visible?(l(),h("div",a({key:0,ref:i.containerRef,id:t.$id,class:t.cx("root"),onClick:e[0]||(e[0]=function(){return i.onOverlayClick&&i.onOverlayClick.apply(i,arguments)})},t.ptmi("root")),[t.$slots.start?(l(),h("div",a({key:0,class:t.cx("start")},t.ptm("start")),[g(t.$slots,"start")],16)):I("",!0),w(d,a({ref:i.menubarRef,id:t.$id+"_list",class:t.cx("rootList"),tabindex:t.disabled?-1:t.tabindex,role:"menubar","aria-label":t.ariaLabel,"aria-labelledby":t.ariaLabelledby,"aria-disabled":t.disabled||void 0,"aria-orientation":"vertical","aria-activedescendant":o.focused?i.focusedItemId:void 0,menuId:t.$id,focusedItemId:o.focused?i.focusedItemId:void 0,items:i.processedItems,templates:t.$slots,activeItemPath:o.activeItemPath,level:0,visible:o.submenuVisible,pt:t.pt,unstyled:t.unstyled,onFocus:i.onFocus,onBlur:i.onBlur,onKeydown:i.onKeyDown,onItemClick:i.onItemClick,onItemMouseenter:i.onItemMouseEnter,onItemMousemove:i.onItemMouseMove},t.ptm("rootList")),null,16,["id","class","tabindex","aria-label","aria-labelledby","aria-disabled","aria-activedescendant","menuId","focusedItemId","items","templates","activeItemPath","visible","pt","unstyled","onFocus","onBlur","onKeydown","onItemClick","onItemMouseenter","onItemMousemove"]),t.$slots.end?(l(),h("div",a({key:1,class:t.cx("end")},t.ptm("end")),[g(t.$slots,"end")],16)):I("",!0)],16,Le)):I("",!0)]}),_:3},16,["onEnter","onAfterEnter","onLeave","onAfterLeave"])]}),_:3},8,["appendTo","disabled"])}H.render=we;var Pe=`
    .p-splitbutton {
        display: inline-flex;
        position: relative;
        border-radius: dt('splitbutton.border.radius');
    }

    .p-splitbutton-button.p-button {
        border-start-end-radius: 0;
        border-end-end-radius: 0;
        border-inline-end: 0 none;
    }

    .p-splitbutton-button.p-button:focus-visible,
    .p-splitbutton-dropdown.p-button:focus-visible {
        z-index: 1;
    }

    .p-splitbutton-button.p-button:not(:disabled):hover,
    .p-splitbutton-button.p-button:not(:disabled):active {
        border-inline-end: 0 none;
    }

    .p-splitbutton-dropdown.p-button {
        border-start-start-radius: 0;
        border-end-start-radius: 0;
    }

    .p-splitbutton .p-menu {
        min-width: 100%;
    }

    .p-splitbutton-fluid {
        display: flex;
    }

    .p-splitbutton-rounded .p-splitbutton-dropdown.p-button {
        border-start-end-radius: dt('splitbutton.rounded.border.radius');
        border-end-end-radius: dt('splitbutton.rounded.border.radius');
    }

    .p-splitbutton-rounded .p-splitbutton-button.p-button {
        border-start-start-radius: dt('splitbutton.rounded.border.radius');
        border-end-start-radius: dt('splitbutton.rounded.border.radius');
    }

    .p-splitbutton-raised {
        box-shadow: dt('splitbutton.raised.shadow');
    }
`,Ce={root:function(e){var n=e.instance,r=e.props;return["p-splitbutton p-component",{"p-splitbutton-raised":r.raised,"p-splitbutton-rounded":r.rounded,"p-splitbutton-fluid":n.hasFluid}]},pcButton:"p-splitbutton-button",pcDropdown:"p-splitbutton-dropdown"},Se=V.extend({name:"splitbutton",style:Pe,classes:Ce}),Me={name:"BaseSplitButton",extends:D,props:{label:{type:String,default:null},icon:{type:String,default:null},model:{type:Array,default:null},autoZIndex:{type:Boolean,default:!0},baseZIndex:{type:Number,default:0},appendTo:{type:[String,Object],default:"body"},disabled:{type:Boolean,default:!1},fluid:{type:Boolean,default:null},class:{type:null,default:null},style:{type:null,default:null},buttonProps:{type:null,default:null},menuButtonProps:{type:null,default:null},menuButtonIcon:{type:String,default:void 0},dropdownIcon:{type:String,default:void 0},severity:{type:String,default:null},raised:{type:Boolean,default:!1},rounded:{type:Boolean,default:!1},text:{type:Boolean,default:!1},outlined:{type:Boolean,default:!1},size:{type:String,default:null},plain:{type:Boolean,default:!1}},style:Se,provide:function(){return{$pcSplitButton:this,$parentInstance:this}}},xe={name:"SplitButton",extends:Me,inheritAttrs:!1,emits:["click"],inject:{$pcFluid:{default:null}},data:function(){return{isExpanded:!1}},mounted:function(){var e=this;this.$watch("$refs.menu.visible",function(n){e.isExpanded=n})},methods:{onDropdownButtonClick:function(e){e&&e.preventDefault(),this.$refs.menu.toggle({currentTarget:this.$el,relatedTarget:this.$refs.button.$el}),this.isExpanded=this.$refs.menu.visible},onDropdownKeydown:function(e){(e.code==="ArrowDown"||e.code==="ArrowUp")&&(this.onDropdownButtonClick(),e.preventDefault())},onDefaultButtonClick:function(e){this.isExpanded&&this.$refs.menu.hide(e),this.$emit("click",e)}},computed:{containerClass:function(){return[this.cx("root"),this.class]},hasFluid:function(){return P(this.fluid)?!!this.$pcFluid:this.fluid}},components:{PVSButton:X,PVSMenu:H,ChevronDownIcon:Q}},Ke=["data-p-severity"];function Ae(t,e,n,r,o,i){var d=L("PVSButton"),p=L("PVSMenu");return l(),h("div",a({class:i.containerClass,style:t.style},t.ptmi("root"),{"data-p-severity":t.severity}),[w(d,a({type:"button",class:t.cx("pcButton"),label:t.label,disabled:t.disabled,severity:t.severity,text:t.text,icon:t.icon,outlined:t.outlined,size:t.size,fluid:t.fluid,"aria-label":t.label,onClick:i.onDefaultButtonClick},t.buttonProps,{pt:t.ptm("pcButton"),unstyled:t.unstyled}),F({default:v(function(){return[g(t.$slots,"default")]}),_:2},[t.$slots.icon?{name:"icon",fn:v(function(u){return[g(t.$slots,"icon",{class:S(u.class)},function(){return[M("span",a({class:[t.icon,u.class]},t.ptm("pcButton").icon,{"data-pc-section":"buttonicon"}),null,16)]})]}),key:"0"}:void 0]),1040,["class","label","disabled","severity","text","icon","outlined","size","fluid","aria-label","onClick","pt","unstyled"]),w(d,a({ref:"button",type:"button",class:t.cx("pcDropdown"),disabled:t.disabled,"aria-haspopup":"true","aria-expanded":o.isExpanded,"aria-controls":o.isExpanded?t.$id+"_overlay":void 0,onClick:i.onDropdownButtonClick,onKeydown:i.onDropdownKeydown,severity:t.severity,text:t.text,outlined:t.outlined,size:t.size,unstyled:t.unstyled},t.menuButtonProps,{pt:t.ptm("pcDropdown")}),{icon:v(function(u){return[g(t.$slots,t.$slots.dropdownicon?"dropdownicon":"menubuttonicon",{class:S(u.class)},function(){return[(l(),b(C(t.menuButtonIcon||t.dropdownIcon?"span":"ChevronDownIcon"),a({class:[t.dropdownIcon||t.menuButtonIcon,u.class]},t.ptm("pcDropdown").icon,{"data-pc-section":"menubuttonicon"}),null,16,["class"]))]})]}),_:3},16,["class","disabled","aria-expanded","aria-controls","onClick","onKeydown","severity","text","outlined","size","unstyled","pt"]),w(p,{ref:"menu",id:t.$id+"_overlay",model:t.model,popup:!0,autoZIndex:t.autoZIndex,baseZIndex:t.baseZIndex,appendTo:t.appendTo,unstyled:t.unstyled,pt:t.ptm("pcMenu")},F({_:2},[t.$slots.menuitemicon?{name:"itemicon",fn:v(function(u){return[g(t.$slots,"menuitemicon",{item:u.item,class:S(u.class)})]}),key:"0"}:void 0,t.$slots.item?{name:"item",fn:v(function(u){return[g(t.$slots,"item",{item:u.item,hasSubmenu:u.hasSubmenu,label:u.label,props:u.props})]}),key:"1"}:void 0]),1032,["id","model","autoZIndex","baseZIndex","appendTo","unstyled","pt"])],16,Ke)}xe.render=Ae;export{xe as s};
