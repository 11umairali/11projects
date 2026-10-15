import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const view = fs.readFileSync(new URL('../../resources/views/inbox/alpine-inbox.blade.php', import.meta.url), 'utf8');
function inbox(fetch = async () => ({ok:true,json:async () => ({data:[],has_more:false})})) {
    const script = view.slice(view.indexOf('function inboxApp()'), view.indexOf('</script>', view.indexOf('function inboxApp()')));
    const context = vm.createContext({document:{querySelector:()=>null,hidden:false},window:{},URLSearchParams,fetch,console,Promise,Map,Set,setTimeout,clearInterval});
    vm.runInContext(script,context);
    return context.inboxApp();
}

test('inbox fallback refreshes existing-thread replies and preserves pagination and active selection', async () => {
    const app = inbox();
    app.page=3; app.activeId=7;
    const calls=[];
    app.loadConversations=async (...args)=>calls.push(['list',...args]);
    app.loadSidebar=async ()=>calls.push(['sidebar']);
    app.refreshActiveMessages=async ()=>calls.push(['messages']);
    await app.poll();
    assert.deepEqual(calls,[['list',false,true],['sidebar'],['messages']]);
    assert.equal(app.page,3); assert.equal(app.activeId,7);
});

test('stale silent list responses cannot replace a newer filter result', async () => {
    const pending=[];
    const app=inbox(url=>new Promise(resolve=>pending.push({url,resolve})));
    app.page=2;
    const old=app.loadConversations(false,true);
    assert.match(pending[0].url,/through_page=2/);
    app.folder='sent'; app.page=1;
    const latest=app.loadConversations();
    pending[1].resolve({ok:true,json:async()=>({data:[{id:8}],has_more:false})}); await latest;
    pending[0].resolve({ok:true,json:async()=>({data:[{id:1}],has_more:true})}); await old;
    assert.equal(app.conversations[0].id,8); assert.equal(app.loading,false);
});

test('open notification center refreshes contents and unread decreases without repeat request toasts', async () => {
    const layout=fs.readFileSync(new URL('../../resources/views/components/layouts/app.blade.php',import.meta.url),'utf8');
    const start=layout.indexOf('                            async pollUnreadCount()');
    const method=layout.slice(start,layout.indexOf('                         }"',start)).replace(/\{\{.*?\}\}/g, '/notifications');
    const events=[];
    let response={unread_count:2,notifications:[{id:'old',read:true,type:'general'},{id:'new',read:false,type:'friend_request',title:'Request'}]};
    const c=vm.createContext({Set,fetch:async()=>({ok:true,json:async()=>response}),window:{dispatchEvent:e=>events.push(e)},CustomEvent:class{constructor(type,init){this.type=type;this.detail=init.detail;}}});
    const bell=vm.runInContext('({'+method+'})',c);
    Object.assign(bell,{notifications:[{id:'old'}],loaded:true,open:true,unreadCount:5,polling:false});
    await bell.pollUnreadCount(); await bell.pollUnreadCount();
    assert.equal(bell.unreadCount,2); assert.equal(bell.notifications.length,2); assert.equal(events.length,1);
    response={unread_count:0,notifications:[]}; await bell.pollUnreadCount();
    assert.equal(bell.unreadCount,0); assert.equal(bell.notifications.length,0);
});

test('Echo subscribes privately and reconnect/navigation refreshes without retaining the previous account', () => {
    const windowEvents={},documentEvents={},connections=[],dispatches=[];
    let cfg={user:1,workspace:7,key:'synthetic',cluster:'mt1',tls:true,auth:'/broadcasting/auth'};
    class Echo {constructor(){this.channels=[];this.connector={pusher:{connection:{bind:(name,fn)=>this.connected=fn}}};connections.push(this);} private(channel){this.channels.push(channel);return {listen(){return this;},subscribed(fn){this.ready=fn;return this;}};} disconnect(){this.closed=true;} }
    const c=vm.createContext({Echo,Pusher:class{},JSON,CustomEvent:class {constructor(type,init){this.type=type;this.detail=init?.detail;}},setTimeout:fn=>{fn();return 1;},clearTimeout:()=>{},window:{Livewire:{dispatch:e=>dispatches.push(e)},addEventListener:(name,fn)=>windowEvents[name]=fn,dispatchEvent:e=>{dispatches.push(e.type);windowEvents[e.type]?.(e);}},document:{hidden:false,querySelector:selector=>selector.includes('dahi-realtime')?{content:JSON.stringify(cfg)}:{content:'csrf'},addEventListener:(name,fn)=>documentEvents[name]=fn}});
    const source=fs.readFileSync(new URL('../../resources/js/realtime.js',import.meta.url),'utf8').replace(/^import .*;\n/gm,'');
    vm.runInContext(source,c);
    assert.deepEqual(connections[0].channels,['user.1','workspace.7']);
    connections[0].connected(); assert.ok(dispatches.includes('realtime-refresh'));
    cfg={...cfg,user:2,workspace:8};documentEvents['livewire:navigated']();
    assert.equal(connections[0].closed,true);assert.deepEqual(connections[1].channels,['user.2','workspace.8']);
    windowEvents['dahi:sync']({detail:{type:'friend_request'}});assert.ok(dispatches.includes('dahi:realtime'));
});

test('header notification Alpine component remains valid as a complete expression', () => {
    const layout=fs.readFileSync(new URL('../../resources/views/components/layouts/app.blade.php',import.meta.url),'utf8');
    const marker=layout.indexOf('unreadCount: {{');
    const start=layout.lastIndexOf('x-data="',marker)+8;
    const end=layout.indexOf('x-init="pollUnreadCount()',marker);
    const expression=layout.slice(start,end).trim().replace(/"$/, '').replace(/\{\{.*?\}\}/g,'0');
    const component=vm.runInNewContext('('+expression+')');
    assert.equal(typeof component.pollUnreadCount,'function');
    assert.equal(typeof component.destroy,'function');
});

function friendSearch(fetch) {
    const page = fs.readFileSync(new URL('../../resources/views/friends/_search.blade.php', import.meta.url), 'utf8');
    const source = page.slice(page.indexOf('function friendSearch()'), page.indexOf('</script>'))
        .replace(/@js\(__\('([^']*)'\)\)/g, (_, text) => JSON.stringify(text));
    const intervals = [], cleared = [], events = [];
    const c = vm.createContext({fetch, document: {hidden: false, querySelector: () => ({content: 'csrf'})},
        window: {dispatchEvent: e => events.push(e)}, CustomEvent: class {constructor(type, init) {this.type = type; this.detail = init.detail;}},
        alert: () => {}, setInterval: fn => {intervals.push(fn); return intervals.length;}, clearInterval: id => cleared.push(id), setTimeout, clearTimeout});
    vm.runInContext(source, c);
    return {app: c.friendSearch(), intervals, cleared, events};
}
const settle = () => new Promise(resolve => setImmediate(resolve));

test('friend search refreshes accepted requests without retyping and releases its polling timer', async () => {
    let relation = 'sent';
    const {app, intervals, cleared} = friendSearch(async () => ({ok: true, json: async () => ({data: [{id: 2, name: 'Alice', relation}]})}));
    app.q = 'Alice'; app.init(); app.go(); await settle();
    assert.equal(app.res[0].relation, 'sent');
    relation = 'friend'; intervals[0](); await settle();
    assert.equal(app.res[0].relation, 'friend');
    app.destroy(); assert.deepEqual(cleared, [1]);
});

test('an old friend search cannot restore stale results after the query is cleared', async () => {
    let resolve;
    const {app} = friendSearch(() => new Promise(r => resolve = r));
    app.q = 'Alice'; app.go();
    app.q = ''; app.type();
    resolve({ok: true, json: async () => ({data: [{id: 2, relation: 'sent'}]})}); await settle();
    assert.equal(app.res.length, 0); assert.equal(app.busy, false);
});

test('header queues an invalidation during a fetch and toasts new email failures once', async () => {
    const layout = fs.readFileSync(new URL('../../resources/views/components/layouts/app.blade.php', import.meta.url), 'utf8');
    const start = layout.indexOf('                            async pollUnreadCount()');
    const method = layout.slice(start, layout.indexOf('                         }"', start)).replace(/\{\{.*?\}\}/g, '/notifications');
    const pending = [], events = [];
    const c = vm.createContext({Set, fetch: () => new Promise(resolve => pending.push(resolve)),
        window: {dispatchEvent: event => events.push(event)}, CustomEvent: class {constructor(type, init) {this.type = type; this.detail = init.detail;}}});
    const bell = vm.runInContext('({' + method + '})', c);
    Object.assign(bell, {notifications: [], loaded: true, polling: false, refreshQueued: false});
    const first = bell.pollUnreadCount();
    await bell.pollUnreadCount();
    pending.shift()({ok: true, json: async () => ({notifications: [], unread_count: 0})});
    await first;
    assert.equal(pending.length, 1, 'the event arriving during the request triggers a second fetch');
    pending.shift()({ok: true, json: async () => ({notifications: [{id: 'mail-failure', type: 'email_failed', title: 'Email failed', read: false}], unread_count: 1})});
    await settle();
    assert.equal(bell.unreadCount, 1);
    assert.equal(events.length, 1);
    assert.equal(events[0].detail.message, 'Email failed');
    bell.destroyed = true;
    await bell.pollUnreadCount();
    assert.equal(pending.length, 0);
});

test('friend chat coalesces events during fetch and disposes stale page listeners', async () => {
    const pending = [], timers = new Map(), nodes = new Map();
    const target = () => ({listeners: new Map(), addEventListener(name, fn) {this.listeners.set(name, fn);}, removeEventListener(name, fn) {if (this.listeners.get(name) === fn) this.listeners.delete(name);}});
    const window = target(), document = target();
    const root = {getAttribute: name => name === 'data-user-id' ? '2' : null};
    document.querySelector = selector => selector === '[data-friend-chat]' ? root : null;
    document.getElementById = id => {
        if (!nodes.has(id)) nodes.set(id, {...target(), style: {}, classList: {toggle() {}, add() {}, remove() {}}, scrollHeight: 10, scrollTop: 0, clientHeight: 10});
        return nodes.get(id);
    };
    const c = vm.createContext({window, document, fetch: () => new Promise(resolve => pending.push(resolve)),
        setInterval: fn => {timers.set(1, fn); return 1;}, clearInterval: id => timers.delete(id), setTimeout, clearTimeout, console});
    vm.runInContext(fs.readFileSync(new URL('../../public/js/friend-chat.js', import.meta.url), 'utf8'), c);
    assert.equal(pending.length, 1);
    window.listeners.get('dahi:sync')();
    pending.shift()({json: async () => ({data: [], presence: {label: 'Online', online: true}})});
    await settle();
    assert.equal(pending.length, 1);
    assert.equal(nodes.get('fc-status').textContent, 'Online');
    document.listeners.get('livewire:navigating')();
    assert.equal(timers.size, 0);
    assert.equal(window.listeners.size, 0);
    assert.equal(document.listeners.size, 0);
    pending.shift()({json: async () => ({data: [], presence: {label: 'Stale', online: false}})});
    await settle();
    assert.equal(nodes.get('fc-status').textContent, 'Online');
});
