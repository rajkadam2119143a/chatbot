/**
 * Policy Assistant - guided chatbot for adding, editing, searching, archiving and
 * restoring policies. Server side: custom/chatbot/policy-chatbot.php.
 *
 * Every conversation step is an awaited "ask": the flow functions below read top to
 * bottom like the conversation itself. Typed text and tapped quick replies both resolve
 * the current ask. All text is inserted with textContent, never as HTML.
 */
(function () {
	'use strict';

	var cfg = window.HR_CHATBOT;
	var root = document.getElementById('hr-chatbot');
	if (!cfg || !root) {
		return;
	}

	var CANCEL = { cancelled: true };
	var BULK_MAX = 100;     // same limit as the server (HR_CHATBOT_BULK_MAX)
	var HEALTH_PAGE = 6;    // policies shown at a time in the health check
	var OFFICE = ['doc', 'docx', 'xls', 'xlsx', 'csv'];
	var ALLOWED = ['pdf'].concat(OFFICE);
	var RESET = /^(menu|main menu|cancel|start over|restart|home)$/i;
	var CHIP = {
		skip: { label: 'Skip', value: 'skip' },
		keep: { label: 'Keep current', value: 'keep' },
		clear: { label: 'Clear it', value: 'clear' },
		today: { label: 'Today', value: 'today' }
	};

	var pending = null;   // the ask currently waiting for an answer
	var flowId = 0;
	var busy = false;
	var started = false;

	//------------------------------------------------------------------------
	// DOM
	//------------------------------------------------------------------------

	function el(tag, cls, text) {
		var n = document.createElement(tag);
		if (cls) { n.className = cls; }
		if (text != null) { n.textContent = text; }
		return n;
	}

	var ICON_CHAT = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
	var ICON_HOME = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>';
	var ICON_CLOSE = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19"/></svg>';
	var ICON_HISTORY = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
	var ICON_SEND ='<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/></svg>';

	root.innerHTML =
		'<button type="button" class="hrcb-launcher" aria-expanded="false" aria-controls="hrcb-panel">' + ICON_CHAT + '<span>Policy Assistant</span></button>' +
		'<section class="hrcb-panel" id="hrcb-panel" role="dialog" aria-label="Policy Assistant">' +
			'<div class="hrcb-header">' +
				'<div class="hrcb-header-text"><div class="hrcb-title">Policy Assistant</div><div class="hrcb-subtitle">' + (cfg.canManage ? 'Add, edit, search, archive and organise policies' : 'Ask a question about our policies') + '</div></div>' +
				'<button type="button" class="hrcb-icon-btn" data-act="history" aria-label="Chat history" title="Chat history">' + ICON_HISTORY + '</button>' +
				'<button type="button" class="hrcb-icon-btn" data-act="menu" aria-label="Main menu" title="Main menu">' + ICON_HOME + '</button>' +
				'<button type="button" class="hrcb-icon-btn" data-act="close" aria-label="End chat and close" title="End chat and close">' + ICON_CLOSE + '</button>' +
			'</div>' +
			'<div class="hrcb-history" aria-label="Chat history"></div>' +
			'<div class="hrcb-log" role="log" aria-live="polite"></div>' +
			'<form class="hrcb-composer">' +
				'<label class="hrcb-sr" for="hrcb-input">Your message</label>' +
				'<textarea id="hrcb-input" class="hrcb-input" rows="1" placeholder="Type a message"></textarea>' +
				'<button type="submit" class="hrcb-send" aria-label="Send">' + ICON_SEND + '</button>' +
			'</form>' +
		'</section>';

	var launcher = root.querySelector('.hrcb-launcher');
	var log = root.querySelector('.hrcb-log');
	var form = root.querySelector('.hrcb-composer');
	var input = root.querySelector('.hrcb-input');
	var sendBtn = root.querySelector('.hrcb-send');
	var menuBtn = root.querySelector('[data-act="menu"]');
	var historyBtn = root.querySelector('[data-act="history"]');
	var historyEl = root.querySelector('.hrcb-history');

	function scrollDown() { log.scrollTop = log.scrollHeight; }

	//------------------------------------------------------------------------
	// Chats: the one on screen is saved in this browser as it goes, so closing the window (X) ends it
	// and it stays in "Chat history" for HISTORY_DAYS days. An old chat opens as a read-only transcript.
	//------------------------------------------------------------------------

	var HISTORY_DAYS = 10;
	var MAX_CHATS = 30;
	var MAX_MSGS = 300;
	var STORE_KEY = 'hrcb_chats_' + (cfg.userId || 0);
	var chat = null;        // the chat on screen: {id, title, t0, t1, msgs: [outerHTML of each message or card]}
	var viewing = false;    // an old chat is on screen
	var saveTimer = null;

	function loadChats() {
		try {
			var list = JSON.parse(window.localStorage.getItem(STORE_KEY));
			if (!Array.isArray(list)) { return []; }
			var min = Date.now() - HISTORY_DAYS * 864e5;
			return list.filter(function (c) { return c && c.id && c.title && c.t1 > min && Array.isArray(c.msgs); });
		} catch (e) {
			return [];   // storage blocked or unreadable: no history
		}
	}

	function writeChats(list) {
		for (var tries = 0; tries < 6; tries++) {
			try {
				window.localStorage.setItem(STORE_KEY, JSON.stringify(list));
				return;
			} catch (e) {
				if (list.length <= 1) { return; }
				list.pop();   // storage full: drop the oldest chat and try again
			}
		}
	}

	function saveChat() {
		saveTimer = null;
		if (!chat || !chat.title || viewing) { return; }   // a chat the user never wrote in is not kept
		var list = loadChats().filter(function (c) { return c.id !== chat.id; });
		list.push(chat);
		list.sort(function (a, b) { return b.t1 - a.t1; });
		writeChats(list.slice(0, MAX_CHATS));
	}

	function flushSave() {
		if (saveTimer) { clearTimeout(saveTimer); }
		saveChat();
	}

	/** Keeps a copy of everything shown in the conversation (not the quick replies, not the typing dots). */
	function record(node) {
		if (!chat || viewing || !node.outerHTML || node.querySelector('.hrcb-typing')) { return; }
		if (chat.msgs.length < MAX_MSGS) { chat.msgs.push(node.outerHTML); }
		chat.t1 = Date.now();
		if (!saveTimer) { saveTimer = setTimeout(saveChat, 400); }
	}

	var KEEP_TAGS = { DIV: 1, SPAN: 1, STRONG: 1, BR: 1, A: 1, BUTTON: 1, DL: 1, DT: 1, DD: 1, P: 1 };

	/** Rebuilds a saved message from its HTML, element by element: only known tags and attributes survive, and buttons are inert. */
	function rebuild(src) {
		if (src.nodeType === 3) { return document.createTextNode(src.nodeValue); }
		if (src.nodeType !== 1 || !KEEP_TAGS[src.tagName]) { return null; }
		var n = document.createElement(src.tagName.toLowerCase());
		n.className = String(src.getAttribute('class') || '').replace(/[^\w\- ]/g, '');
		if (src.tagName === 'A') {
			var href = src.getAttribute('href') || '';
			if (/^(https?:\/\/|\/)/i.test(href)) {
				n.href = href;
				n.target = '_blank';
				n.rel = 'noopener';
			}
		}
		if (src.tagName === 'BUTTON') {
			n.type = 'button';
			n.disabled = true;   // its action belonged to that moment
		}
		[].forEach.call(src.childNodes, function (c) {
			var k = rebuild(c);
			if (k) { n.appendChild(k); }
		});
		return n;
	}

	function newChat() {
		chat = { id: Date.now().toString(36) + Math.random().toString(36).slice(2, 6), title: '', t0: Date.now(), t1: Date.now(), msgs: [] };
	}

	function whenLabel(t) {
		var d = new Date(t);
		var n = new Date();
		var days = Math.round((new Date(n.getFullYear(), n.getMonth(), n.getDate()) - new Date(d.getFullYear(), d.getMonth(), d.getDate())) / 864e5);
		var hm = pad(d.getHours()) + ':' + pad(d.getMinutes());
		if (days === 0) { return 'Today, ' + hm; }
		if (days === 1) { return 'Yesterday, ' + hm; }
		return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear() + ', ' + hm;
	}

	/** content: string (\n = line break) or array of strings / {b: 'bold text'} / DOM nodes. */
	function fill(node, content) {
		[].concat(content).forEach(function (part) {
			if (part && part.nodeType) {
				node.appendChild(part);
			} else if (part && part.b != null) {
				node.appendChild(el('strong', '', part.b));
			} else {
				String(part).split('\n').forEach(function (line, i) {
					if (i) { node.appendChild(document.createElement('br')); }
					if (line) { node.appendChild(document.createTextNode(line)); }
				});
			}
		});
	}

	/** Adds a node to the conversation, keeping the open quick replies at the bottom. */
	function append(node) {
		record(node);
		log.appendChild(node);
		if (pending && pending.row && pending.row.parentNode === log) {
			log.appendChild(pending.row);
		}
		scrollDown();
	}

	function say(content, kind) {
		var m = el('div', 'hrcb-msg hrcb-msg-bot' + (kind ? ' hrcb-msg-' + kind : ''));
		fill(m, content);
		append(m);
		return m;
	}

	function userSay(text) {
		// The first real thing the user says names the chat (not "Main menu", "Yes", "Cancel" ...).
		if (chat && !chat.title && !viewing && !/^(main menu|menu|back|cancel|skip|yes\b.*|no\b.*)$/i.test(text)) { chat.title = truncate(text, 48); }
		append(el('div', 'hrcb-msg hrcb-msg-user', text));
	}

	function autosize() {
		input.style.height = 'auto';
		input.style.height = Math.min(input.scrollHeight, 128) + 'px';
		input.style.overflowY = input.scrollHeight > 128 ? 'auto' : 'hidden';
	}

	function setBusy(b) {
		busy = b;
		input.disabled = b;
		sendBtn.disabled = b;
		menuBtn.disabled = b;
	}

	//------------------------------------------------------------------------
	// Asking questions
	//------------------------------------------------------------------------

	/**
	 * spec.type    'text' | 'choice' | 'file'
	 * spec.text    what the bot says
	 * spec.options choice: [{label, value, primary, match}]  (typed text is matched to them)
	 * spec.chips   extra quick replies for any type: [{label, value}]
	 * spec.validate text: fn(raw) -> {value} | {error}
	 * spec.accept  file: allowed extensions
	 * spec.filterable choice: typing narrows the options
	 * spec.token   cards that carry a "Select" button answer the ask with the same token
	 * Resolves {value} (typed text / chosen file), {chip} (quick reply) or {policy} (card).
	 */
	function ask(spec) {
		return new Promise(function (resolve, reject) {
			if (spec.text) { say(spec.text, spec.kind); }

			var p = { spec: spec, resolve: resolve, reject: reject, row: null, chips: null, actions: null, fileInput: null };

			if (spec.type === 'file') {
				p.fileInput = el('input');
				p.fileInput.type = 'file';
				p.fileInput.hidden = true;
				p.fileInput.accept = spec.accept.map(function (e) { return '.' + e; }).join(',');
				p.fileInput.addEventListener('change', function () { onFilePicked(p); });
				root.appendChild(p.fileInput);
			}

			// row = whole answer area; chips = the options; actions = a separate row (e.g. Save / Cancel)
			p.row = el('div', 'hrcb-ask');
			p.chips = el('div', 'hrcb-chips' + (spec.list ? ' hrcb-list' : ''));
			p.row.appendChild(p.chips);
			if (spec.actions && spec.actions.length) {
				p.actions = el('div', 'hrcb-chips hrcb-actions');
				p.row.appendChild(p.actions);
			}
			renderChips(p, spec.options);
			log.appendChild(p.row);

			pending = p;
			input.placeholder = spec.type === 'text' ? (spec.placeholder || 'Type your answer') : 'Pick an option, or type "menu"';
			scrollDown();
			if (spec.type === 'text' && !busy) { input.focus(); }
		});
	}

	function renderChips(p, options) {
		var spec = p.spec;
		p.chips.textContent = '';
		if (p.actions) { p.actions.textContent = ''; }

		if (spec.type === 'file') {
			var pick = el('button', 'hrcb-chip hrcb-chip-primary', 'Choose file');
			pick.type = 'button';
			pick.addEventListener('click', function () { p.fileInput.click(); });
			p.chips.appendChild(pick);
		}

		(options || []).forEach(function (o) {
			addChip(p, p.chips, o);
		});

		if (spec.filterable && options && options !== spec.options) {
			addChip(p, p.chips, { label: 'Show all', value: '__all' });
		}

		(spec.chips || []).forEach(function (o) {
			addChip(p, p.chips, o);
		});

		(spec.actions || []).forEach(function (o) {
			addChip(p, p.actions, o);
		});

		p.chips.classList.toggle('hrcb-empty', !p.chips.children.length);
		p.row.classList.toggle('hrcb-empty', !p.chips.children.length && !(p.actions && p.actions.children.length));
	}

	function addChip(p, container, o) {
		var b = el('button', 'hrcb-chip' + (o.primary ? ' hrcb-chip-primary' : ''), o.label);
		b.type = 'button';
		if (o.depth) { b.style.setProperty('--depth', o.depth); }
		b.addEventListener('click', function () {
			if (o.value === '__all') {
				renderChips(p, p.spec.options);
				return;
			}
			settle(p, { chip: o.value }, o.echo || o.label);
		});
		container.appendChild(b);
	}

	function settle(p, result, echo) {
		if (pending !== p) { return; }
		pending = null;
		clearUi(p);
		if (echo) { userSay(echo); }
		input.placeholder = 'Type a message';
		p.resolve(result);
	}

	function clearUi(p) {
		if (p.row && p.row.parentNode) { p.row.parentNode.removeChild(p.row); }
		if (p.fileInput && p.fileInput.parentNode) { p.fileInput.parentNode.removeChild(p.fileInput); }
	}

	function cancelPending() {
		if (!pending) { return; }
		var p = pending;
		pending = null;
		clearUi(p);
		p.reject(CANCEL);
	}

	function extOf(name) {
		var i = String(name).lastIndexOf('.');
		return i < 0 ? '' : String(name).slice(i + 1).toLowerCase();
	}

	function onFilePicked(p) {
		var file = p.fileInput.files[0];
		if (!file || pending !== p) { return; }

		var ext = extOf(file.name);
		var problem = '';
		if (p.spec.accept.indexOf(ext) < 0) {
			problem = 'That file type is not allowed. Allowed types: ' + p.spec.accept.join(', ').toUpperCase() + '.';
		} else if (!file.size) {
			problem = 'That file is empty. Please choose another one.';
		} else if (file.size > cfg.maxBytes) {
			problem = 'That file is ' + mb(file.size) + ', but the limit is ' + mb(cfg.maxBytes) + '. Please choose a smaller one.';
		}

		if (problem) {
			p.fileInput.value = '';
			say(problem, 'error');
			return;
		}
		settle(p, { value: file }, file.name);
	}

	//------------------------------------------------------------------------
	// Typed input
	//------------------------------------------------------------------------

	function handleInput() {
		var text = input.value.trim();
		if (!text || busy) { return; }
		input.value = '';
		autosize();

		userSay(text);

		if (RESET.test(text)) {
			run(menuFlow);
			return;
		}
		if (cfg.canManage && (!pending || pending.spec.menu)) {
			var cmd = parseCommand(text);
			if (cmd) {
				run(commandFlow, cmd);
				return;
			}
		}
		if (!pending) {
			run(menuFlow);
			return;
		}

		var p = pending;
		var type = p.spec.type;

		if (type === 'text') {
			var r = p.spec.validate ? p.spec.validate(text) : { value: text };
			if (r.error) {
				say(r.error, 'error');
				return;
			}
			settle(p, { value: r.value });
		} else if (type === 'choice') {
			answerChoiceByText(p, text);
		} else {
			say('Please use the "Choose file" button below, or type "menu" to go back.', 'hint');
		}
	}

	function answerChoiceByText(p, text) {
		var t = text.toLowerCase();
		var opts = (p.spec.options || []).concat(p.spec.actions || []);

		// `echo` is the full name shown when the option is picked (a category's "Parent > Child" path)
		var hits = opts.filter(function (o) { return o.label.toLowerCase() === t || (o.echo && o.echo.toLowerCase() === t); });
		if (!hits.length) {
			hits = opts.filter(function (o) {
				return o.label.toLowerCase().indexOf(t) > -1 || (o.echo && o.echo.toLowerCase().indexOf(t) > -1) || (o.match && o.match.test(text));
			});
		}

		if (hits.length === 1) {
			settle(p, { chip: hits[0].value });
		} else if (hits.length > 1 && p.spec.filterable) {
			say('Which one do you mean?');
			renderChips(p, hits);
			scrollDown();
		} else if (hits.length > 1) {
			say('Please pick one of the options.', 'hint');
		} else {
			say('I did not find a match. Please pick one of the options, or type "menu".', 'hint');
		}
	}

	function askText(text, o) { return ask(Object.assign({ type: 'text', text: text }, o)); }
	function askChoice(text, options, o) { return ask(Object.assign({ type: 'choice', text: text, options: options }, o)); }

	//------------------------------------------------------------------------
	// Server calls
	//------------------------------------------------------------------------

	function mb(bytes) {
		return (bytes / 1048576).toFixed(1).replace(/\.0$/, '') + ' MB';
	}

	function buildForm(task, fields, files) {
		var fd = new FormData();
		fd.append('hr_chatbot', task);
		fd.append('nonce', cfg.nonce);
		Object.keys(fields || {}).forEach(function (k) {
			if (fields[k] !== null && fields[k] !== undefined) { fd.append(k, fields[k]); }
		});
		Object.keys(files || {}).forEach(function (k) {
			if (files[k]) { fd.append(k, files[k], files[k].name); }
		});
		return fd;
	}

	/** One request. Always resolves to {ok, message, code, ...}; never throws. */
	async function send(task, fields, files) {
		try {
			var res = await fetch(cfg.endpoint, { method: 'POST', body: buildForm(task, fields, files), credentials: 'same-origin' });
			var body = await res.text();
			try {
				return JSON.parse(body);
			} catch (e) {
				return unexpectedReply(res.status, body);
			}
		} catch (e) {
			return { ok: false, code: 'network', message: 'I could not reach the server, so nothing was saved. Please check your connection and try again.' };
		}
	}

	/** The server answered with something other than the assistant's JSON. Say why that usually happens. */
	function unexpectedReply(status, body) {
		var why;
		if (status === 413) {
			why = 'The server rejected the upload because it is too large (HTTP 413).';
		} else if (status === 401 || status === 403) {
			why = 'The server refused the request (HTTP ' + status + '). Your login may have expired, or a firewall blocked the upload. Refresh the page and try again.';
		} else if (status >= 500) {
			why = 'The server hit an error while handling the request (HTTP ' + status + '). The policy may be partly saved, so please check it before trying again.';
		} else if (/<html|<!doctype/i.test(body)) {
			why = 'The request did not reach the Policy Assistant: the server sent back a normal web page instead (HTTP ' + status + '). This usually means the upload is bigger than the server allows (' + mb(cfg.postMax || cfg.maxBytes) + ' per request), or your session expired. Refresh the page and try again.';
		} else {
			why = 'The server sent a reply I could not read (HTTP ' + status + ').';
		}
		return { ok: false, code: 'unexpected', message: why };
	}

	async function call(task, fields, files) {
		var total = Object.keys(files || {}).reduce(function (sum, k) { return sum + (files[k] ? files[k].size : 0); }, 0);
		if (cfg.postMax && total > cfg.postMax) {
			return { ok: false, code: 'toolarge', message: 'The files together are ' + mb(total) + ', but the server accepts at most ' + mb(cfg.postMax) + ' per upload. Please use smaller files.' };
		}

		setBusy(true);
		var fid = flowId;
		var typing = say(el('span', 'hrcb-typing', ''));
		typing.firstChild.innerHTML = '<span></span><span></span><span></span>';
		try {
			var data = await send(task, fields, files);
			// The security token can expire or change while the page stays open; the reply carries a new one.
			if (data.code === 'nonce' && data.nonce) {
				cfg.nonce = data.nonce;
				data = await send(task, fields, files);
			}
			if (data.nonce) { cfg.nonce = data.nonce; }
			// The chat was closed or restarted while waiting: do not let the old conversation write into the new one.
			if (fid !== flowId) { throw CANCEL; }
			return data;
		} finally {
			typing.remove();
			setBusy(false);
		}
	}

	/** Tell the admin what went wrong and what to do about it. */
	function reportFailure(res, doing) {
		var advice = {
			validation: 'Please correct that and try again.',
			nonce: 'Please refresh the page and try again.',
			forbidden: 'Your account does not have permission for this.',
			notfound: 'It may have been removed or changed by someone else.',
			stale: 'Open "Revert / undo a mistake" from the main menu to see its latest state.'
		}[res.code] || '';

		say([{ b: 'I could not ' + doing + '.' }, '\n' + (res.message || 'Unknown error.') + (advice ? ' ' + advice : '')], 'error');
		if (res.code) { say('Reference: ' + res.code, 'hint'); }
	}

	//------------------------------------------------------------------------
	// Display helpers
	//------------------------------------------------------------------------

	function catName(id) {
		var c = cfg.categories.filter(function (x) { return x.id === Number(id); })[0];
		return c ? c.name : '';
	}

	function truncate(s, n) {
		s = String(s);
		return s.length > n ? s.slice(0, n - 1).trim() + '…' : s;
	}

	function btn(label, o) {
		var b;
		if (o.href) {
			b = el('a', 'hrcb-btn', label);
			b.href = o.href;
			b.target = '_blank';
			b.rel = 'noopener';
		} else {
			b = el('button', 'hrcb-btn' + (o.primary ? ' hrcb-btn-primary' : ''), label);
			b.type = 'button';
			b.addEventListener('click', function () { if (!busy) { o.onClick(); } });
		}
		return b;
	}

	/** A Purpose/Scope that adds something: many policies just repeat their name there. */
	function hasOwnPurpose(p) {
		var purpose = String(p.purpose || '').trim().toLowerCase();
		return purpose !== '' && purpose !== String(p.title || '').trim().toLowerCase();
	}

	/** actions: [{label, href | onClick, primary}] */
	function policyCard(p, actions) {
		var card = el('div', 'hrcb-card');
		card.appendChild(el('div', 'hrcb-card-title', p.title));

		var meta = [p.category || 'No category', p.status === 'private' ? 'Private' : 'Public'];
		if (p.date) { meta.push('Policy date ' + p.date); }
		card.appendChild(el('div', 'hrcb-card-meta', meta.join(' · ')));

		if (hasOwnPurpose(p)) { card.appendChild(el('div', 'hrcb-card-text', truncate(p.purpose, 140))); }

		if (actions && actions.length) {
			var row = el('div', 'hrcb-card-actions');
			actions.forEach(function (a) { row.appendChild(btn(a.label, a)); });
			card.appendChild(row);
		}
		append(card);
	}

	/** rows: [[label, value]] */
	function summaryCard(rows) {
		var card = el('div', 'hrcb-card');
		var dl = el('dl', 'hrcb-summary');
		rows.forEach(function (r) {
			dl.appendChild(el('dt', '', r[0]));
			dl.appendChild(el('dd', '', r[1] || '—'));
		});
		card.appendChild(dl);
		append(card);
	}

	//------------------------------------------------------------------------
	// Field collectors. Each returns the new value; `cur` is the current one (edit)
	// or undefined (add). `edit` adds "Keep current" style quick replies.
	//------------------------------------------------------------------------

	async function getTitle(cur, edit) {
		var r = await askText(
			edit ? ['Current name: ', { b: cur }, '\nWhat should the new name be?'] : ['What is the ', { b: 'name' }, ' of the policy?'],
			{
				placeholder: 'Policy name',
				chips: edit ? [CHIP.keep] : [],
				validate: function (v) { return v.length >= 3 ? { value: v } : { error: 'The name must be at least three characters long.' }; }
			}
		);
		return r.chip ? cur : r.value;
	}

	async function getCategory(cur, edit) {
		var options = await categoryChoices();
		if (!options.length) {
			say('There are no categories yet. Choose "Manage categories" in the main menu to create one first.', 'error');
			throw CANCEL;
		}
		var r = await askChoice(
			(edit && cur ? ['Current category: ', { b: catName(cur) }, '\nWhich category should it go in?'] : ['Which ', { b: 'category' }, ' does it belong to?']).concat(' Tap one, or type to narrow the list.'),
			options,
			{ filterable: true, list: true, chips: edit ? [CHIP.keep] : [] }
		);
		return r.chip === 'keep' ? cur : Number(r.chip);
	}

	async function getKeywords(cur, edit) {
		var r = await askText(
			edit ? ['Current keywords: ', { b: cur || 'none' }, '\nType the new keywords, separated by commas.'] : ['Any ', { b: 'keywords' }, '? Type them separated by commas (for example: leave, attendance, holiday).'],
			{
				placeholder: 'keyword one, keyword two',
				chips: edit ? [CHIP.keep].concat(cur ? [CHIP.clear] : []) : [CHIP.skip],
				validate: function (v) {
					var seen = {};
					var list = v.split(',').map(function (k) { return k.trim(); }).filter(function (k) {
						var key = k.toLowerCase();
						if (!k || seen[key]) { return false; }
						seen[key] = true;
						return true;
					});
					return list.length ? { value: list.join(', ') } : { error: 'Please type at least one keyword, or tap Skip.' };
				}
			}
		);
		if (r.chip === 'keep') { return cur; }
		return r.chip ? '' : r.value;
	}

	async function getPurpose(cur, edit) {
		var r = await askText(
			edit ? ['Current Purpose/Scope: ', { b: truncate(cur || 'none', 200) }, '\nType the new text (60 words at most).'] : ['What is the ', { b: 'purpose or scope' }, ' of the policy? A short description, 60 words at most.'],
			{
				placeholder: 'Purpose / scope',
				chips: edit ? [CHIP.keep].concat(cur ? [CHIP.clear] : []) : [CHIP.skip],
				validate: function (v) {
					var words = v.split(/\s+/).length;
					return words <= 60 ? { value: v } : { error: 'That is ' + words + ' words. Please shorten it to 60 words or fewer.' };
				}
			}
		);
		if (r.chip === 'keep') { return cur; }
		return r.chip ? '' : r.value;
	}

	async function getFiles(cur, edit) {
		var current = edit && cur ? '\nCurrent file: ' + cur : '';
		var r = await ask({
			type: 'file',
			text: ['Please upload the ', { b: 'policy file' }, '. Allowed: PDF, DOC, DOCX, XLS, XLSX or CSV, up to ' + mb(cfg.maxBytes) + '.' + current],
			accept: ALLOWED,
			chips: edit ? [CHIP.keep] : []
		});
		if (r.chip) { return cur; }

		var main = r.value;
		var pdf = null;
		if (extOf(main.name) !== 'pdf') {
			var r2 = await ask({
				type: 'file',
				text: ['That is a ' + extOf(main.name).toUpperCase() + ' file, so I also need the ', { b: 'PDF version' }, ' of the same document (up to ' + mb(cfg.maxBytes) + ').'],
				accept: ['pdf']
			});
			pdf = r2.value;
		}
		return { main: main, pdf: pdf };
	}

	async function getAccess(cur, edit) {
		var r = await askChoice(
			(edit ? ['Currently ', { b: cur === 'private' ? 'Private' : 'Public' }, '. '] : []).concat('Who should be able to see it? Public policies are visible to everyone; Private policies are hidden from regular users.'),
			[{ label: 'Public', value: 'public' }, { label: 'Private', value: 'private' }]
		);
		return r.chip;
	}

	function pad(n) { return (n < 10 ? '0' : '') + n; }

	function parseDate(s) {
		var m = s.trim().match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/);
		if (!m) { return null; }
		var d = +m[1], mo = +m[2], y = +m[3];
		var dt = new Date(y, mo - 1, d);
		if (dt.getFullYear() !== y || dt.getMonth() !== mo - 1 || dt.getDate() !== d) { return null; }
		return pad(d) + '/' + pad(mo) + '/' + y;
	}

	async function getDate(cur, edit) {
		var r = await askText(
			(edit ? ['Current policy date: ', { b: cur || 'not set' }, '\n'] : []).concat(['What is the ', { b: 'policy date' }, ' (the date it was last updated)? Use dd/mm/yyyy.']),
			{
				placeholder: 'dd/mm/yyyy',
				chips: edit ? [CHIP.today, CHIP.keep].concat(cur ? [CHIP.clear] : []) : [CHIP.today, CHIP.skip],
				validate: function (v) {
					var d = parseDate(v);
					return d ? { value: d } : { error: 'That is not a valid date. Please use dd/mm/yyyy, for example 15/03/2026.' };
				}
			}
		);
		if (r.chip === 'keep') { return cur; }
		if (r.chip === 'today') {
			var n = new Date();
			return pad(n.getDate()) + '/' + pad(n.getMonth() + 1) + '/' + n.getFullYear();
		}
		return r.chip ? '' : r.value;
	}

	var FIELDS = [
		{ key: 'title', label: 'Name', get: getTitle },
		{ key: 'cat', label: 'Category', get: getCategory },
		{ key: 'keywords', label: 'Keywords', get: getKeywords },
		{ key: 'purpose', label: 'Purpose/Scope', get: getPurpose },
		{ key: 'files', label: 'Policy file', get: getFiles },
		{ key: 'access', label: 'Access', get: getAccess },
		{ key: 'date', label: 'Policy date', get: getDate }
	];

	function fieldOptions() {
		return FIELDS.map(function (f) { return { label: f.label, value: f.key }; });
	}

	function fieldByKey(key) {
		return FIELDS.filter(function (f) { return f.key === key; })[0];
	}

	function filesLabel(files) {
		if (!files) { return ''; }
		return files.main.name + (files.pdf ? ' + ' + files.pdf.name + ' (PDF)' : '');
	}

	/** Values as the server expects them. `files` are only sent when new ones were chosen. */
	function payload(v) {
		return {
			title: v.title,
			cat: v.cat,
			post_tags: v.keywords || '',
			post_excerpt: v.purpose || '',
			post_status: v.access,
			post_policy_date: v.date || ''
		};
	}

	function fileParts(files) {
		return files ? { post_file: files.main, post_file1: files.pdf } : {};
	}

	//------------------------------------------------------------------------
	// Browsing policies (search / pick one / archived)
	//------------------------------------------------------------------------

	var BROWSE_PROMPT = {
		search: 'What should I look for? Type part of a policy name or a keyword.',
		pick: 'Which policy? Type part of its name or a keyword.',
		archived: 'These are the archived policies. Type a name or keyword to narrow them down, or show the latest.'
	};

	/**
	 * mode 'search'   cards get View / Edit / Archive buttons
	 * mode 'pick'     cards get a Select button; resolves with the chosen policy
	 * mode 'archived' cards get View / Restore buttons
	 * Resolves null when the user goes back to the menu.
	 */
	/**
	 * opts.q       a search already typed (skips the first question)
	 * opts.access  'public' | 'private': only those
	 * opts.auto    with opts.q: when exactly one policy matches, resolve with it straight away
	 */
	async function browse(mode, opts) {
		opts = opts || {};
		var archived = mode === 'archived';
		var first = true;
		var preset = opts.q !== undefined;
		var autoPick = false;

		for (;;) {
			var q = '';
			var cat = 0;
			if (preset) {
				q = opts.q;
				autoPick = !!opts.auto;
				preset = false;
				first = false;
			} else {
				autoPick = false;
				var r = await askText(first ? BROWSE_PROMPT[mode] : 'Type another name or keyword.', {
					placeholder: 'Policy name or keyword',
					chips: [
						{ label: archived ? 'Show latest archived' : 'Show latest', value: 'latest' },
						{ label: 'Browse by category', value: 'cat' },
						{ label: 'Main menu', value: 'menu' }
					]
				});
				first = false;

				if (r.chip === 'menu') { return null; }
				if (r.chip === 'cat') {
					var c = await askChoice('Which category?', await categoryChoices(), {
						filterable: true,
						list: true,
						chips: [{ label: 'Main menu', value: 'menu' }]
					});
					if (c.chip === 'menu') { return null; }
					cat = Number(c.chip);
				} else if (r.value) {
					q = r.value;
				}
			}

			var page = 1;
			var token = {};   // one per search, so "Show more" keeps earlier Select buttons working
			for (;;) {
				var data = await call('search', { q: q, cat: cat, archived: archived ? 1 : '', access: opts.access || '', page: page });
				if (!data.ok) {
					reportFailure(data, 'search the policies');
					return null;
				}
				if (!data.items.length) {
					say(page === 1 ? 'I could not find any ' + (archived ? 'archived ' : '') + 'policies matching that.' : 'That is all of them.');
					break;
				}

				if (autoPick && page === 1 && data.total === 1) {
					say(['Found ', { b: data.items[0].title }, '.']);
					return data.items[0];
				}

				if (page === 1) {
					say('Found ' + data.total + (data.total === 1 ? ' policy' : ' policies') + (q ? ' matching “' + q + '”' : '') + '.');
				}

				data.items.forEach(function (p) { policyCard(p, cardActions(mode, p, token)); });

				var options = [];
				if (data.has_more) { options.push({ label: 'Show more', value: 'more' }); }
				options.push({ label: 'Search again', value: 'again' }, { label: 'Main menu', value: 'menu' });

				var a = await askChoice(mode === 'pick' ? ['Tap ', { b: 'Select' }, ' on the policy you mean.'] : 'What next?', options, { token: token });
				if (a.policy) { return a.policy; }
				if (a.chip === 'more') { page++; continue; }
				if (a.chip === 'menu') { return null; }
				break;
			}
		}
	}

	function cardActions(mode, p, token) {
		var view = { label: 'View', href: p.url };
		if (mode === 'pick') {
			return [{
				label: 'Select', primary: true,
				onClick: function () {
					if (pending && pending.spec.token === token) { settle(pending, { policy: p }, p.title); }
				}
			}, view];
		}
		if (mode === 'archived') {
			return [{ label: 'Restore', primary: true, onClick: function () { run(restoreFlow, p); } }, view];
		}
		return [
			view,
			{ label: 'Edit', primary: true, onClick: function () { run(editFlow, p); } },
			{ label: 'History', onClick: function () { run(historyFlow, p); } },
			{ label: 'Archive', onClick: function () { run(archiveFlow, p); } }
		];
	}

	//------------------------------------------------------------------------
	// Flows
	//------------------------------------------------------------------------

	var FLOWS = {
		add: addFlow, edit: editFlow, search: searchFlow, archive: archiveFlow, restore: restoreFlow, undo: undoFlow, categories: categoriesFlow,
		tools: toolsFlow, health: healthFlow, bulk: bulkFlow, export: exportFlow, schedules: schedulesFlow, gaps: gapsFlow, ask: staffFlow, history: historyFlow
	};

	async function menuFlow(greeting) {
		if (!cfg.canManage) { return staffFlow(greeting); }   // staff only get the question mode
		say(typeof greeting === 'string' ? greeting : 'What would you like to do? You can also just type it, for example "archive leave policy" or "find travel".');
		var r = await askChoice(null, [
			{ label: 'Add a new policy', value: 'add', match: /^(?!.*\bcategor).*\b(add|new|create|upload)\b/i },
			{ label: 'Edit a policy', value: 'edit', match: /^(?!.*\b(undo|revert|rollback|roll back|categor\w*)\b).*\b(edit|update|change|modify)\b/i },
			{ label: 'Search policies', value: 'search', match: /\b(search|find|look ?up|list)\b/i },
			{ label: 'Archive a policy', value: 'archive', match: /^(?!.*\bcategor).*\b(archive|remove|delete)\b/i },
			{ label: 'View archives / Restore', value: 'restore', match: /\b(restore|unarchive|archives|bring back)\b/i },
			{ label: 'Manage categories (menu)', value: 'categories', match: /\b(categor(y|ies)|sections?|sidebar|left menu)\b/i },
			{ label: 'Revert / undo a mistake', value: 'undo', match: /\b(undo|revert|rollback|roll back|mistake|wrong)\b/i },
			{ label: 'Ask a policy question', value: 'ask', match: /\b(ask|question)\b/i },
			{ label: 'More tools', value: 'tools', match: /\b(tools?|more|health|bulk|export|csv|download|schedul\w*|gaps?)\b/i }
		], { menu: true });
		run(FLOWS[r.chip]);
	}

	/** Health check, bulk actions, export, scheduled archives and the searches that found nothing. */
	async function toolsFlow() {
		var r = await askChoice('Which tool would you like?', [
			{ label: 'Policy health check', value: 'health', match: /\b(health|check|audit|missing)\b/i },
			{ label: 'Policy history (earlier files)', value: 'history', match: /\b(history|versions?|earlier|older|previous|old file)\b/i },
			{ label: 'Bulk actions', value: 'bulk', match: /\b(bulk|many|several|multiple)\b/i },
			{ label: 'Export to CSV', value: 'export', match: /\b(export|csv|download|excel|spreadsheet)\b/i },
			{ label: 'Scheduled archives', value: 'schedules', match: /\b(schedul\w*|later|future)\b/i },
			{ label: 'Searches with no results', value: 'gaps', match: /\b(gaps?|no results?|nothing found|missing searches)\b/i }
		], { actions: [{ label: 'Main menu', value: 'menu' }] });
		return r.chip === 'menu' ? menuFlow() : FLOWS[r.chip]();
	}

	//------------------------------------------------------------------------
	// Typed commands: "archive leave policy", "edit travel", "show private policies", "export" ...
	// Only used from the main menu; anything not recognised is matched against the menu as before.
	//------------------------------------------------------------------------

	var NOT_A_POLICY = /^(archives?|archived|categor\w*|sections?|menu|undo|mistake|tools?|more)\b|categor/i;

	/** "the leave policy" -> "leave": drops the word policy and leading filler, keeps the rest as typed. */
	function cleanQuery(s) {
		return s.replace(/\b(policy|policies|please)\b/gi, ' ')
			.replace(/^(?:\s*\b(?:the|a|an|my|me|named|called|for|of)\b)+/i, ' ')
			.replace(/\s+/g, ' ').trim();
	}

	/** Returns {flow, q?, access?} or null. */
	function parseCommand(text) {
		var t = text.trim();
		var m;
		if (/\?$/.test(t) && t.split(/\s+/).length >= 3) { return { flow: 'ask', q: t }; }   // a question
		m = t.match(/^ask\s+(.+)$/i);
		if (m) { return { flow: 'ask', q: m[1] }; }
		if (/^(health|health check|policy health( check)?|audit)$/i.test(t)) { return { flow: 'health' }; }
		if (/^(bulk|bulk actions?)$/i.test(t)) { return { flow: 'bulk' }; }
		if (/^(history|versions?)$/i.test(t)) { return { flow: 'history' }; }
		m = t.match(/^(?:history|versions?)\s+(?:of\s+|for\s+)?(.+)$/i);
		if (m) {
			var hq = cleanQuery(m[1]);
			if (hq.length >= 2) { return { flow: 'history', q: hq }; }
		}
		if (/^(export|download|csv|(export|download)( to| as)?( a)? ?(csv|excel|spreadsheet|file))$/i.test(t)) { return { flow: 'export' }; }
		if (/^(scheduled( archives?)?|schedules?)$/i.test(t)) { return { flow: 'schedules' }; }
		if (/^(gaps|no results|searches with no results|missing searches)$/i.test(t)) { return { flow: 'gaps' }; }

		m = t.match(/^(?:show|list|find)\s+(?:all\s+)?(private|public)\b(.*)$/i);
		if (m) { return { flow: 'search', access: m[1].toLowerCase(), q: cleanQuery(m[2]) }; }

		m = t.match(/^(archive|restore|unarchive|edit|update|modify|change|find|search|show|view|open)\s+(.+)$/i);
		if (m) {
			var q = cleanQuery(m[2]);
			if (q.length < 2 || NOT_A_POLICY.test(q)) { return null; }
			var verb = m[1].toLowerCase();
			var flow = { archive: 'archive', restore: 'restore', unarchive: 'restore', edit: 'edit', update: 'edit', modify: 'edit', change: 'edit' }[verb] || 'search';
			return { flow: flow, q: q };
		}
		return null;
	}

	async function commandFlow(cmd) {
		var p;
		switch (cmd.flow) {
			case 'ask':
				return staffFlow('Anything else you would like to know?', await answerQuestion(cmd.q));
			case 'search':
				say(cmd.access ? 'Looking for ' + cmd.access + ' policies' + (cmd.q ? ' matching “' + cmd.q + '”' : '') + '.' : 'Searching for “' + cmd.q + '”.');
				await browse('search', { q: cmd.q || '', access: cmd.access });
				return backToMenu();
			case 'history':
				p = cmd.q ? await browse('pick', { q: cmd.q, auto: true }) : null;
				return cmd.q ? (p ? historyFlow(p) : backToMenu()) : historyFlow();
			case 'edit':
				p = await browse('pick', { q: cmd.q, auto: true });
				return p ? editFlow(p) : backToMenu();
			case 'archive':
				p = await browse('pick', { q: cmd.q, auto: true });
				return p ? archiveFlow(p) : backToMenu();
			case 'restore':
				p = await browse('archived', { q: cmd.q, auto: true });
				return p ? restoreFlow(p) : backToMenu();
			default:
				return FLOWS[cmd.flow]();
		}
	}

	async function backToMenu(message) {
		if (message) { say(message); }
		return menuFlow('Is there anything else I can help you with?');
	}

	async function searchFlow() {
		await browse('search');
		return backToMenu();
	}

	/** preset: {cat} adds the policy to that category without asking for it (used from Manage categories). */
	//------------------------------------------------------------------------
	// Unfinished "Add a policy" is kept in this browser (not the files), so a refresh or a
	// closed tab does not lose the answers.
	//------------------------------------------------------------------------

	var DRAFT_KEY = 'hrcb_add_draft_' + (cfg.userId || 0);
	var DRAFT_DAYS = 7;

	function loadDraft() {
		try {
			var d = JSON.parse(window.localStorage.getItem(DRAFT_KEY));
			if (d && d.v && d.v.title && Date.now() - d.t < DRAFT_DAYS * 864e5) { return d; }
		} catch (e) { /* storage blocked or unreadable: no draft */ }
		return null;
	}

	function saveDraft(v, done) {
		try {
			var keep = {};
			Object.keys(v).forEach(function (k) { if (k !== 'files') { keep[k] = v[k]; } });
			window.localStorage.setItem(DRAFT_KEY, JSON.stringify({ t: Date.now(), v: keep, done: done }));
		} catch (e) { /* storage blocked or full: carry on without a draft */ }
	}

	function clearDraft() {
		try { window.localStorage.removeItem(DRAFT_KEY); } catch (e) { /* ignore */ }
	}

	async function addFlow(preset) {
		var presetCat = preset && preset.cat ? Number(preset.cat) : 0;

		var v = {};
		var resumed = {};   // fields answered in an earlier, unfinished attempt
		var draft = presetCat ? null : loadDraft();
		if (draft) {
			var ago = Math.max(1, Math.round((Date.now() - draft.t) / 60000));
			var when = ago < 60 ? ago + ' min ago' : ago < 1440 ? Math.round(ago / 60) + ' h ago' : Math.round(ago / 1440) + ' d ago';
			say(['You have an unfinished policy: ', { b: draft.v.title }, ' (started ' + when + ').']);
			var back = await askChoice('Would you like to carry on with it?', [
				{ label: 'Continue where I left off', value: 'resume', primary: true },
				{ label: 'Start a new one', value: 'fresh' }
			]);
			if (back.chip === 'resume') {
				v = draft.v;
				(draft.done || []).forEach(function (k) { if (k !== 'files') { resumed[k] = true; } });
				if (resumed.cat && !catName(v.cat)) { delete resumed.cat; }   // that category is gone
				say('Okay. I will only ask for what is still missing (the file has to be chosen again).');
			} else {
				clearDraft();
			}
		}
		if (!draft || !Object.keys(resumed).length) {
			say('Great, let\'s add a new policy. I\'ll ask a few questions. You can type "menu" at any time to cancel.');
		}
		if (presetCat) { say(['It will go in ', { b: catName(presetCat) }, '.']); }

		if (!Object.keys(resumed).length) { await autofillStep(v, resumed, presetCat); }

		var answered = Object.keys(resumed).filter(function (k) { return k !== 'files'; });   // a chosen file is not kept in the draft
		if (answered.length) { saveDraft(v, answered); }
		for (var i = 0; i < FIELDS.length; i++) {
			var f = FIELDS[i];
			if (f.key === 'cat' && presetCat) {
				v.cat = presetCat;
				continue;
			}
			if (resumed[f.key]) { continue; }
			v[f.key] = await f.get(undefined, false);
			if (f.key !== 'files') {
				answered.push(f.key);
				saveDraft(v, answered);
			}

			if (f.key === 'title') {
				if (await titleClash(v.title)) { i--; }
			}
		}

		for (;;) {
			say('Here is what I have. Please check it:');
			summaryCard([
				['Name', v.title], ['Category', catName(v.cat)], ['Keywords', v.keywords], ['Purpose', v.purpose],
				['File', filesLabel(v.files)], ['Access', v.access === 'private' ? 'Private' : 'Public'], ['Policy date', v.date]
			]);

			var r = await askChoice('Shall I add this policy?', [
				{ label: 'Yes, add policy', value: 'yes', primary: true },
				{ label: 'Change something', value: 'change' },
				{ label: 'Cancel', value: 'cancel' }
			]);

			if (r.chip === 'cancel') {
				clearDraft();
				return backToMenu('Okay, nothing was added.');
			}

			if (r.chip === 'change') {
				var which = await askChoice('Which detail would you like to change?', fieldOptions(), { actions: [{ label: 'Back', value: 'back' }] });
				if (which.chip !== 'back') {
					var def = fieldByKey(which.chip);
					v[def.key] = await def.get(v[def.key], false);
					if (def.key !== 'files') { saveDraft(v, answered); }
				}
				continue;
			}

			var res = await call('add', payload(v), fileParts(v.files));
			var changing = false;
			while (!res.ok && !changing) {
				reportFailure(res, 'add the policy');
				var retry = await askChoice('What would you like to do?', [
					{ label: 'Try again', value: 'retry', primary: true },
					{ label: 'Change something', value: 'change' },
					{ label: 'Cancel', value: 'cancel' }
				]);
				if (retry.chip === 'cancel') {
					clearDraft();
					return backToMenu('Okay, nothing was added.');
				}
				if (retry.chip === 'change') {
					var which2 = await askChoice('Which detail would you like to change?', fieldOptions());
					var def2 = fieldByKey(which2.chip);
					v[def2.key] = await def2.get(v[def2.key], false);
					changing = true;
				} else {
					// After an unreadable reply the policy may have been saved anyway: look before re-adding.
					var saved = (res.code === 'unexpected' || res.code === 'network') ? await findExactTitle(v.title) : null;
					if (saved) {
						clearDraft();
						say(['It looks like ', { b: v.title }, ' was saved after all, so I did not add it again.'], 'success');
						policyCard(saved, [{ label: 'View', href: saved.url }, { label: 'Edit', onClick: function () { run(editFlow, saved); } }]);
						return backToMenu();
					}
					res = await call('add', payload(v), fileParts(v.files));
				}
			}
			if (changing) { continue; }

			clearDraft();
			say(['Done! ', { b: v.title }, ' has been added.'], 'success');
			if (res.policy) { policyCard(res.policy, [{ label: 'View', href: res.policy.url }, { label: 'Edit', onClick: function () { run(editFlow, res.policy); } }]); }
			var next = await askChoice('What next?', [{ label: 'Add another policy', value: 'add', primary: true }, { label: 'Main menu', value: 'menu' }]);
			return next.chip === 'add' ? addFlow() : menuFlow('Is there anything else I can help you with?');
		}
	}

	/** True when a policy with that name exists and the admin wants a different name. */
	async function titleClash(title) {
		var existing = await findExactTitle(title);
		if (!existing) { return false; }
		say(['A policy called ', { b: existing.title }, ' already exists (' + (existing.category || 'no category') + ').']);
		var dup = await askChoice('Do you still want to add a new one?', [
			{ label: 'Use a different name', value: 'rename' },
			{ label: 'Add anyway', value: 'go' }
		]);
		return dup.chip === 'rename';
	}

	/**
	 * Offers to read the policy file first and suggest the details (the file is read on this server only).
	 * Fills `v` and marks what was accepted in `resumed`, so those questions are skipped.
	 */
	async function autofillStep(v, resumed, presetCat) {
		var go = await askChoice(
			['Do you have the policy file ready? I can ', { b: 'read it and suggest' }, ' the name, category, keywords, purpose and date, so you only have to check them. The file is read on this server only; nothing is sent elsewhere.'],
			[
				{ label: 'Upload the file first', value: 'upload', primary: true, match: /\b(upload|file|yes|read|suggest|auto\w*)\b/i },
				{ label: 'I\'ll type the details', value: 'manual', match: /\b(no|manual\w*|type|myself)\b/i }
			]
		);
		if (go.chip === 'manual') { return; }

		var files = await getFiles(undefined, false);
		v.files = files;
		resumed.files = true;

		// Only PDF, DOCX and CSV can be read; for a Word/Excel file the PDF version is read instead.
		var readable = ['pdf', 'docx', 'csv'];
		var source = readable.indexOf(extOf(files.main.name)) > -1 ? files.main : files.pdf;
		if (!source || readable.indexOf(extOf(source.name)) < 0) {
			say('I cannot read that kind of file, so I will ask for the details instead. Your file is kept.', 'hint');
			return;
		}

		var res = await call('analyze', {}, { file: source });
		if (!res.ok) {
			reportFailure(res, 'read the file');
			say('No problem, I will ask for the details instead. Your file is kept.');
			return;
		}

		var s = res.suggestion;
		if (presetCat) { s.cat = 0; }
		if (s.cat && !catName(s.cat)) { s.cat = 0; }
		if (!res.read) { say('I could not read any text in that file (it may be a scanned image), so the only thing I can suggest is a name from the file name.', 'hint'); }

		var rows = [['Name', s.title], ['Category', s.cat_name], ['Keywords', s.keywords], ['Purpose', s.purpose], ['Policy date', s.date]]
			.filter(function (r) { return r[1]; });
		if (!rows.length || (!s.cat && !s.keywords && !s.purpose && !s.date && !s.title)) {
			say('I could not find anything to suggest, so I will ask for the details.');
			return;
		}
		if (s.cat_name && !s.cat) { rows = rows.filter(function (r) { return r[0] !== 'Category'; }); }

		say('Here is what I found. These are only suggestions: you can change anything afterwards.');
		summaryCard(rows);
		var use = await askChoice('Use these suggestions?', [
			{ label: 'Yes, use them', value: 'yes', primary: true },
			{ label: 'No, I\'ll type them', value: 'no' }
		]);
		if (use.chip === 'no') { return; }

		if (s.title) { v.title = s.title; resumed.title = true; }
		if (s.cat) { v.cat = s.cat; resumed.cat = true; }
		if (s.keywords) { v.keywords = s.keywords; resumed.keywords = true; }
		if (s.purpose) { v.purpose = s.purpose; resumed.purpose = true; }
		if (s.date) { v.date = s.date; resumed.date = true; }

		if (resumed.title && await titleClash(v.title)) { delete resumed.title; }
		say('Thanks. I will only ask for what is still missing, then you can check everything before it is saved.');
	}

	async function findExactTitle(title) {
		var res = await call('search', { q: title });
		if (!res.ok) { return null; }
		var t = title.toLowerCase();
		return res.items.filter(function (p) { return p.title.toLowerCase() === t; })[0] || null;
	}

	async function editFlow(given) {
		var chosen = given && given.id ? given : await browse('pick');
		if (!chosen) { return backToMenu(); }

		var got = await call('get', { id: chosen.id });
		if (!got.ok) {
			reportFailure(got, 'open that policy');
			return backToMenu();
		}
		var p = got.policy;

		say(['Okay, editing ', { b: p.title }, '. Here are its current details:']);
		summaryCard([
			['Name', p.title], ['Category', p.category], ['Keywords', p.keywords], ['Purpose', truncate(p.purpose, 160)],
			['File', p.file], ['PDF', p.pdf], ['Access', p.status === 'private' ? 'Private' : 'Public'], ['Policy date', p.date]
		]);

		var v = { title: p.title, cat: p.category_id, keywords: p.keywords, purpose: p.purpose, access: p.status, date: p.date, files: null };
		var changed = {};   // key -> true
		var original = { title: 'title', cat: 'category_id', keywords: 'keywords', purpose: 'purpose', access: 'status', date: 'date' };
		var current = { files: p.file + (p.pdf && p.pdf !== p.file ? ' + ' + p.pdf : '') };

		for (;;) {
			var options = fieldOptions();
			var hasChanges = Object.keys(changed).length > 0;
			var actions = hasChanges ? [{ label: 'Save changes', value: 'save', primary: true }] : [];
			actions.push({ label: 'Cancel', value: 'cancel' });

			var r = await askChoice(
				hasChanges ? 'Got it. Pick another detail to change, or save your changes.' : 'Which detail would you like to change?',
				options,
				{ actions: actions }
			);

			if (r.chip === 'cancel') { return backToMenu('Okay, no changes were made.'); }

			if (r.chip !== 'save') {
				var def = fieldByKey(r.chip);
				var before = v[def.key];
				var after = await def.get(def.key === 'files' ? current.files : v[def.key], true);
				if (def.key === 'files') {
					if (after && after.main) { v.files = after; changed.files = true; }
				} else if (after !== before) {
					v[def.key] = after;
					changed[def.key] = true;
				}
				continue;
			}

			say('These are the changes I will save:');
			summaryCard(FIELDS.filter(function (f) { return changed[f.key]; }).map(function (f) {
				if (f.key === 'files') { return [f.label, filesLabel(v.files)]; }
				var show = function (val) {
					return f.key === 'cat' ? catName(val) : f.key === 'access' ? (val === 'private' ? 'Private' : 'Public') : truncate(val || '', 120);
				};
				return [f.label, (show(p[original[f.key]]) || '—') + '  →  ' + (show(v[f.key]) || '—')];
			}));

			var ok = await askChoice('Save these changes?', [
				{ label: 'Yes, save', value: 'yes', primary: true },
				{ label: 'Keep editing', value: 'more' },
				{ label: 'Cancel', value: 'cancel' }
			]);
			if (ok.chip === 'cancel') { return backToMenu('Okay, no changes were made.'); }
			if (ok.chip === 'more') { continue; }

			var res = await call('edit', Object.assign({ id: p.id }, payload(v)), fileParts(v.files));
			var keepEditing = false;
			while (!res.ok && !keepEditing) {
				reportFailure(res, 'save the changes to "' + p.title + '"');
				var retry = await askChoice('What would you like to do?', [
					{ label: 'Try again', value: 'retry', primary: true },
					{ label: 'Keep editing', value: 'more' },
					{ label: 'Cancel', value: 'cancel' }
				]);
				if (retry.chip === 'cancel') { return backToMenu('Okay, no changes were made.'); }
				if (retry.chip === 'more') { keepEditing = true; } else { res = await call('edit', Object.assign({ id: p.id }, payload(v)), fileParts(v.files)); }
			}
			if (keepEditing) { continue; }

			say(['Done! ', { b: v.title }, ' has been updated.'], 'success');
			if (res.policy) {
				var done = [{ label: 'View', href: res.policy.url }];
				if (res.undo) { done.push({ label: 'Undo this change', onClick: function () { run(undoFlow, res.policy); } }); }
				policyCard(res.policy, done);
			}
			if (res.undo) { say('Saved the wrong file or detail by mistake? You can undo this change for 24 hours, using the button above or "Revert / undo a mistake" in the main menu.', 'hint'); }
			return backToMenu();
		}
	}

	//------------------------------------------------------------------------
	// Policy history: the files uploaded for a policy, and putting an earlier one back
	//------------------------------------------------------------------------

	function versionCard(v, p) {
		var card = el('div', 'hrcb-card');
		card.appendChild(el('div', 'hrcb-card-title', v.when + (v.current ? ' · Showing now' : '')));
		card.appendChild(el('div', 'hrcb-card-meta', (v.by ? 'Uploaded by ' + v.by + ' · ' : '') + v.main.ext.toUpperCase() + ' ' + v.main.size + (v.pdf ? ' + PDF ' + v.pdf.size : '')));

		var row = el('div', 'hrcb-card-actions');
		if (!v.current) {
			row.appendChild(btn('Restore this one', { primary: true, onClick: function () { run(restoreVersionFlow, { policy: p, version: v }); } }));
		}
		row.appendChild(btn('Open file', { href: v.main.url }));
		if (v.pdf) { row.appendChild(btn('Open PDF', { href: v.pdf.url })); }
		card.appendChild(row);
		append(card);
	}

	/** Lists the stored files of a policy (newest first) with a Restore button on the older ones. */
	async function historyFlow(given) {
		var chosen = given && given.id ? given : await browse('pick');
		if (!chosen) { return backToMenu(); }

		var res = await call('versions', { id: chosen.id });
		if (!res.ok) {
			reportFailure(res, 'open the history');
			return backToMenu();
		}
		var p = res.policy;
		if (!res.versions.length) {
			say(['I found no stored files for ', { b: p.title }, '. Policies added a long time ago, or whose files were removed from the server, have no history.']);
			return backToMenu();
		}
		if (res.versions.length === 1) {
			say(['', { b: p.title }, ' has only one stored file, so there is nothing earlier to go back to.']);
			res.versions.forEach(function (v) { versionCard(v, p); });
			return backToMenu();
		}

		say(['Files uploaded for ', { b: p.title }, ', newest first. Tap ', { b: 'Restore this one' }, ' to make an earlier file the one visitors see:']);
		res.versions.forEach(function (v) { versionCard(v, p); });
		if (res.total > res.versions.length) { say('Showing the ' + res.versions.length + ' newest of ' + res.total + '.', 'hint'); }
		await askChoice(null, [{ label: 'Main menu', value: 'menu' }]);
		return backToMenu();
	}

	async function restoreVersionFlow(arg) {
		var p = arg.policy;
		var v = arg.version;
		var ok = await askChoice(
			['Make the file uploaded on ', { b: v.when }, ' the one visitors see for ', { b: p.title }, '? The file showing now stays stored, so you can switch back, and you can also undo this for 24 hours.'],
			[{ label: 'Yes, restore it', value: 'yes', primary: true }, { label: 'No, leave it', value: 'no' }]
		);
		if (ok.chip === 'no') { return backToMenu('Okay, I left it as it is.'); }

		var res = await call('version_restore', { id: p.id, version: v.key });
		if (!res.ok) {
			reportFailure(res, 'restore that file');
			return backToMenu();
		}
		say(['Done! ', res.message], 'success');
		if (res.policy) {
			var actions = [{ label: 'View', href: res.policy.url }];
			if (res.undo) { actions.push({ label: 'Undo this change', onClick: function () { run(undoFlow, res.policy); } }); }
			policyCard(res.policy, actions);
		}
		return backToMenu();
	}

	/** Rows for the summary: what the policy shows now  →  what it goes back to. */
	function changeRows(item) {
		return item.changes.map(function (r) { return [r[0], (truncate(r[1], 80) || '—') + '  →  ' + (truncate(r[2], 80) || '—')]; });
	}

	function changeCard(item, actions) {
		var card = el('div', 'hrcb-card');
		card.appendChild(el('div', 'hrcb-card-title', item.title));
		card.appendChild(el('div', 'hrcb-card-meta', item.label + ' · ' + item.when + (item.by ? ' by ' + item.by : '') + ' · ' + item.expires + ' left to undo'));
		var dl = el('dl', 'hrcb-summary');
		changeRows(item).forEach(function (r) {
			dl.appendChild(el('dt', '', r[0]));
			dl.appendChild(el('dd', '', r[1]));
		});
		card.appendChild(dl);
		var row = el('div', 'hrcb-card-actions');
		actions.forEach(function (a) { row.appendChild(btn(a.label, a)); });
		card.appendChild(row);
		append(card);
	}

	/**
	 * Everything the assistant changed in the last 24 hours (policy edits, added / archived / restored
	 * policies, category changes), each of which can be undone.
	 * Without an argument: lists them, newest first. With {id} (a policy's last edit) or {change}
	 * (any other change): reviews that one and asks.
	 */
	async function undoFlow(given) {
		var one = given && (given.id || given.change);
		var res = await call('recent', given && given.change ? { change: given.change } : one ? { id: given.id } : {});
		if (!res.ok) {
			reportFailure(res, 'look up the recent changes');
			return backToMenu();
		}
		if (!res.items.length) {
			say(one ? 'There is nothing left to undo there. A change can be undone for 24 hours.' : 'Nothing has been changed with the Policy Assistant in the last 24 hours, so there is nothing to undo.');
			return backToMenu();
		}

		if (!one) {
			say(['Here is everything the Policy Assistant changed in the last 24 hours, newest first. Each card shows what changed. Tap ', { b: 'Undo' }, ' on the one you want to take back:']);
			res.items.forEach(function (item) {
				var actions = [{ label: 'Undo', primary: true, onClick: function () { run(undoFlow, item.kind === 'log' ? { change: item.change } : item.policy); } }];
				if (item.policy) { actions.push({ label: 'View', href: item.policy.url }); }
				changeCard(item, actions);
			});
			if (res.total > res.items.length) { say('Showing the ' + res.items.length + ' most recent of ' + res.total + '.', 'hint'); }
			await askChoice(null, [{ label: 'Main menu', value: 'menu' }]);
			return backToMenu();
		}

		var item = res.items[0];
		say([{ b: item.label }, ': ', { b: item.title }, '. Made ' + item.when + (item.by ? ' by ' + item.by : '') + '. If you undo it, this goes back:']);
		summaryCard(changeRows(item));
		if (item.warn) { say(item.warn, 'hint'); }
		if (item.more) { say('This policy was edited ' + item.more + (item.more === 1 ? ' time' : ' times') + ' before that as well. Those can be undone afterwards, one at a time.', 'hint'); }

		var ok = await askChoice('Undo this change?', [
			{ label: 'Yes, undo it', value: 'yes', primary: true },
			{ label: 'No, keep it as it is', value: 'no' }
		]);
		if (ok.chip === 'no') { return backToMenu('Okay, I left it as it is.'); }

		var done = await catCall('undo', item.kind === 'log' ? { change: item.change } : { id: item.policy.id, snap: item.snap });
		if (!done.ok) {
			reportFailure(done, 'undo "' + item.title + '"');
			return backToMenu();
		}
		say(item.kind === 'log' ? ['Done! ', done.message] : ['Done! ', { b: done.policy.title }, ' is back to how it was before the change.'], 'success');
		if (done.policy) { policyCard(done.policy, [{ label: 'View', href: done.policy.url }]); }
		if (done.more) { say('You can still undo ' + countOf(done.more, 'more change', 'more changes') + ' from "Revert / undo a mistake".', 'hint'); }
		return backToMenu();
	}

	async function archiveFlow(given) {
		var p = given && given.id ? given : await browse('pick');
		if (!p) { return backToMenu(); }

		var r = await askChoice(
			['Archive ', { b: p.title }, '? It will disappear from the policy lists, and you can restore it later from Archives.'],
			[
				{ label: 'Yes, archive it now', value: 'yes', primary: true },
				{ label: 'Archive it on a date', value: 'later', match: /\b(later|date|schedul\w*|on)\b/i },
				{ label: 'No, keep it', value: 'no' }
			]
		);
		if (r.chip === 'no') { return backToMenu('Okay, I left it as it is.'); }
		if (r.chip === 'later') { return scheduleFlow(p); }

		var res = await call('archive', { id: p.id });
		if (!res.ok) {
			reportFailure(res, 'archive "' + p.title + '"');
			return backToMenu();
		}
		say(res.message, 'success');
		var next = await askChoice('What next?', [{ label: 'Archive another', value: 'again' }, { label: 'Main menu', value: 'menu' }]);
		return next.chip === 'again' ? archiveFlow() : menuFlow('Is there anything else I can help you with?');
	}

	async function restoreFlow(given) {
		if (!given || !given.id) {
			// Browsing shows Restore buttons on each card, which start this flow with that policy.
			await browse('archived');
			return backToMenu();
		}

		var r = await askChoice(
			['Restore ', { b: given.title }, ' so it shows in the policy lists again?'],
			[{ label: 'Yes, restore it', value: 'yes', primary: true }, { label: 'No', value: 'no' }]
		);
		if (r.chip === 'no') { return backToMenu('Okay, I left it archived.'); }

		var res = await call('restore', { id: given.id });
		if (!res.ok) {
			reportFailure(res, 'restore "' + given.title + '"');
			return backToMenu();
		}
		say(res.message, 'success');
		if (res.policy) { policyCard(res.policy, [{ label: 'View', href: res.policy.url }, { label: 'Edit', onClick: function () { run(editFlow, res.policy); } }]); }
		return backToMenu();
	}

	//------------------------------------------------------------------------
	// Questions in plain words. Staff only get this mode; admins reach it from the main menu.
	// It finds the matching policies (by name, keywords, category and Purpose/Scope); it does not
	// write answers of its own, so what it shows is always what the policy says.
	//------------------------------------------------------------------------

	function staffCard(p) {
		var card = el('div', 'hrcb-card');
		card.appendChild(el('div', 'hrcb-card-title', p.title));
		var meta = [p.category || 'No category'];
		if (p.status === 'private') { meta.push('Private'); }
		if (p.date) { meta.push('Policy date ' + p.date); }
		card.appendChild(el('div', 'hrcb-card-meta', meta.join(' · ')));
		if (hasOwnPurpose(p)) { card.appendChild(el('div', 'hrcb-card-text', truncate(p.purpose, 220))); }

		var row = el('div', 'hrcb-card-actions');
		row.appendChild(btn('Open policy', { href: p.url, primary: true }));
		if (p.file_url) { row.appendChild(btn('Download', { href: p.file_url })); }
		if (p.pdf_url && p.pdf_url !== p.file_url) { row.appendChild(btn('PDF', { href: p.pdf_url })); }
		if (cfg.canManage) {
			row.appendChild(btn('Edit', { onClick: function () { run(editFlow, p); } }));
			row.appendChild(btn('History', { onClick: function () { run(historyFlow, p); } }));
		}
		card.appendChild(row);
		append(card);
	}

	var STAFF_PAGE = 3;   // matches shown at a time; "Show more" brings the next ones

	/** Shows the next batch of matches and returns the ones still waiting. */
	function showStaffCards(list) {
		list.slice(0, STAFF_PAGE).forEach(staffCard);
		return list.slice(STAFF_PAGE);
	}

	/** Answers a question and returns the matches not shown yet. */
	async function answerQuestion(q) {
		var res = await call('ask', { q: q });
		if (!res.ok) {
			reportFailure(res, 'look that up');
			return [];
		}
		if (!res.items.length) {
			say(['I could not find a policy about that. Try other words (for example "leave" instead of "time off"), or browse by category.' + (cfg.canManage ? '' : ' I have noted the question so the HR team can see what is missing.')]);
			return [];
		}

		var n = countOf(res.total, 'policy', 'policies');
		var listed = res.total > res.items.length ? ' (the best ' + res.items.length + ' can be listed)' : '';
		if (res.weak) {
			say(['I am not sure I understood that, so these may not be what you mean. I found ', { b: n }, listed + ':']);
		} else {
			say(res.total === 1 ? 'This looks like what you need:' : ['I found ', { b: n }, listed + ':']);
		}
		return showStaffCards(res.items);
	}

	/** Lists the policies of a category and returns the ones not shown yet. */
	async function browseForStaff() {
		var options = cfg.categories.map(function (c) { return { label: c.name, value: c.id }; });
		var c = await askChoice('Which category?', options, { filterable: true, list: true, chips: [{ label: 'Back', value: 'back' }] });
		if (c.chip === 'back') { return []; }

		var res = await call('ask', { cat: Number(c.chip) });
		if (!res.ok) {
			reportFailure(res, 'open that category');
			return [];
		}
		if (!res.items.length) {
			say(['There are no policies in ', { b: res.category }, ' yet.']);
			return [];
		}
		say(['In ', { b: res.category }, ' there ' + (res.total === 1 ? 'is ' : 'are ') + countOf(res.total, 'policy', 'policies') + (res.total > res.items.length ? ' (the first ' + res.items.length + ' can be listed)' : '') + ':']);
		return showStaffCards(res.items);
	}

	/** waiting: matches from an earlier answer that "Show more" should still offer. */
	async function staffFlow(greeting, waiting) {
		say(typeof greeting === 'string' ? greeting : 'What would you like to know about our policies?');
		var queue = waiting || [];
		for (;;) {
			var chips = queue.length ? [{ label: 'Show more (' + queue.length + ' left)', value: 'more', primary: true }] : [];
			chips.push({ label: 'Browse by category', value: 'cat' });
			if (cfg.canManage) { chips.push({ label: 'Main menu', value: 'menu' }); }

			var r = await askText(null, { placeholder: 'For example: how do I apply for maternity leave?', chips: chips });
			if (r.chip === 'menu') { return menuFlow(); }
			if (r.chip === 'more') {
				queue = showStaffCards(queue);
			} else if (r.chip === 'cat') {
				queue = await browseForStaff();
			} else {
				queue = await answerQuestion(r.value);
			}

			say(queue.length
				? 'Tap "Show more" for the other ' + queue.length + ', or ask again with other words.'
				: 'Open a policy for the full details. Not what you meant? Ask again with other words, or browse by category.', 'hint');
		}
	}

	//------------------------------------------------------------------------
	// Scheduled archives
	//------------------------------------------------------------------------

	/** d is dd/mm/yyyy: is it tomorrow or later? */
	function isFutureDate(d) {
		var m = d.split('/');
		var today = new Date();
		today.setHours(0, 0, 0, 0);
		return new Date(+m[2], +m[1] - 1, +m[0]) > today;
	}

	async function scheduleFlow(p) {
		var r = await askText(['On which date should ', { b: p.title }, ' be archived? Use dd/mm/yyyy (tomorrow or later). It is archived just after midnight that day.'], {
			placeholder: 'dd/mm/yyyy',
			chips: [{ label: 'Cancel', value: 'cancel' }],
			validate: function (v) {
				var d = parseDate(v);
				if (!d) { return { error: 'That is not a valid date. Please use dd/mm/yyyy, for example 31/12/2026.' }; }
				return isFutureDate(d) ? { value: d } : { error: 'Please choose tomorrow or a later date.' };
			}
		});
		if (r.chip) { return backToMenu('Okay, I left it as it is.'); }

		var res = await call('schedule', { id: p.id, date: r.value });
		if (!res.ok) {
			reportFailure(res, 'schedule the archive');
			return backToMenu();
		}
		say(res.message, 'success');
		say('Scheduled archives run through the site\'s background tasks, so it is archived on the first page visit after midnight. Until then it stays as it is. You can cancel it under More tools > Scheduled archives.', 'hint');
		return backToMenu();
	}

	async function schedulesFlow() {
		var res = await call('schedules');
		if (!res.ok) {
			reportFailure(res, 'look up the scheduled archives');
			return backToMenu();
		}
		if (!res.items.length) {
			say('No policy is scheduled to be archived. To schedule one, choose "Archive a policy" and then "Archive it on a date".');
			return backToMenu();
		}

		say('These policies will be archived automatically:');
		res.items.forEach(function (item) {
			var card = el('div', 'hrcb-card');
			card.appendChild(el('div', 'hrcb-card-title', item.policy.title));
			card.appendChild(el('div', 'hrcb-card-meta', 'Archived on ' + item.when + (item.by ? ' · set by ' + item.by : '')));
			var row = el('div', 'hrcb-card-actions');
			row.appendChild(btn('Cancel the schedule', { primary: true, onClick: function () { run(cancelSchedule, item.policy); } }));
			row.appendChild(btn('View', { href: item.policy.url }));
			card.appendChild(row);
			append(card);
		});
		await askChoice(null, [{ label: 'Main menu', value: 'menu' }]);
		return backToMenu();
	}

	async function cancelSchedule(p) {
		var res = await call('unschedule', { id: p.id });
		if (!res.ok) {
			reportFailure(res, 'cancel the schedule');
			return backToMenu();
		}
		say(res.message, 'success');
		return schedulesFlow();
	}

	//------------------------------------------------------------------------
	// Policy health check
	//------------------------------------------------------------------------

	async function healthFlow() {
		say('Checking every active policy…');
		var res = await call('health');
		if (!res.ok) {
			reportFailure(res, 'run the health check');
			return backToMenu();
		}
		if (!res.groups.length) {
			say(['I checked ', { b: countOf(res.checked, 'active policy', 'active policies') }, ' and found nothing to fix.'], 'success');
			return backToMenu();
		}

		say(['I checked ', { b: countOf(res.checked, 'active policy', 'active policies') }, '. Here is what needs attention:']);
		for (;;) {
			var options = res.groups.map(function (g) { return { label: g.label + ' (' + g.total + ')', value: g.code }; });
			var r = await askChoice('Which would you like to look at?', options, { actions: [{ label: 'Main menu', value: 'menu' }] });
			if (r.chip === 'menu') { return backToMenu(); }
			await showHealthGroup(res.groups.filter(function (g) { return g.code === r.chip; })[0]);
		}
	}

	async function showHealthGroup(g) {
		say([{ b: g.label }, ': ' + g.hint]);
		var shown = 0;
		for (;;) {
			g.items.slice(shown, shown + HEALTH_PAGE).forEach(function (p) {
				var actions = [{ label: 'Fix', primary: true, onClick: function () { run(editFlow, p); } }, { label: 'View', href: p.url }];
				if (g.code === 'stale') { actions.push({ label: 'Archive', onClick: function () { run(archiveFlow, p); } }); }
				policyCard(p, actions);
			});
			shown += HEALTH_PAGE;

			var options = [];
			if (shown < g.items.length) {
				options.push({ label: 'Show more', value: 'more' });
			} else if (g.total > g.items.length) {
				say('Showing the first ' + g.items.length + ' of ' + g.total + '. Fix some, then run the check again.', 'hint');
			}
			options.push({ label: 'Back to the list', value: 'back' });
			var a = await askChoice(null, options);
			if (a.chip !== 'more') { return; }
		}
	}

	//------------------------------------------------------------------------
	// Bulk actions
	//------------------------------------------------------------------------

	/** One policy with a Select toggle. Returns the function that redraws the button. */
	function selectCard(p, sel, count) {
		var card = el('div', 'hrcb-card');
		card.appendChild(el('div', 'hrcb-card-title', p.title));
		card.appendChild(el('div', 'hrcb-card-meta', [p.category || 'No category', p.status === 'private' ? 'Private' : 'Public'].join(' · ')));

		var row = el('div', 'hrcb-card-actions');
		var b = el('button', 'hrcb-btn', 'Select');
		b.type = 'button';
		function paint() {
			var on = !!sel[p.id];
			b.textContent = on ? 'Selected ✓' : 'Select';
			b.className = 'hrcb-btn' + (on ? ' hrcb-btn-primary' : '');
			b.setAttribute('aria-pressed', on ? 'true' : 'false');
		}
		b.addEventListener('click', function () {
			if (sel[p.id]) {
				delete sel[p.id];
			} else if (count() >= BULK_MAX) {
				say('You can select up to ' + BULK_MAX + ' policies at a time.', 'hint');
				return;
			} else {
				sel[p.id] = p;
			}
			paint();
		});
		row.appendChild(b);
		row.appendChild(btn('View', { href: p.url }));
		card.appendChild(row);
		append(card);
		paint();
		return paint;
	}

	/** Lets the admin tick several policies. Resolves with an array (maybe empty), or null for the main menu. */
	async function pickMany(archived) {
		var sel = {};
		var count = function () { return Object.keys(sel).length; };
		var chosen = function () { return Object.keys(sel).map(function (id) { return sel[id]; }); };
		var first = true;

		for (;;) {
			var q = '';
			var cat = 0;
			var chips = [
				{ label: archived ? 'Show latest archived' : 'Show latest', value: 'latest' },
				{ label: 'Browse by category', value: 'cat' }
			];
			if (count()) { chips.push({ label: 'Done (' + count() + ' selected)', value: 'done', primary: true }); }
			chips.push({ label: 'Main menu', value: 'menu' });

			var r = await askText(
				first ? 'Which policies? Type part of a name or a keyword to find them, then tap Select on each one you want.' : 'Type another name or keyword to add more, or tap Done.',
				{ placeholder: 'Policy name or keyword', chips: chips }
			);
			first = false;

			if (r.chip === 'menu') { return null; }
			if (r.chip === 'done') { return chosen(); }
			if (r.chip === 'cat') {
				var c = await askChoice('Which category?', await categoryChoices(), { filterable: true, list: true, chips: [{ label: 'Main menu', value: 'menu' }] });
				if (c.chip === 'menu') { return null; }
				cat = Number(c.chip);
			} else if (r.value) {
				q = r.value;
			}

			var page = 1;
			for (;;) {
				var data = await call('search', { q: q, cat: cat, archived: archived ? 1 : '', page: page });
				if (!data.ok) {
					reportFailure(data, 'search the policies');
					return null;
				}
				if (!data.items.length) {
					say(page === 1 ? 'I could not find any ' + (archived ? 'archived ' : '') + 'policies matching that.' : 'That is all of them.');
					break;
				}
				if (page === 1) { say('Found ' + countOf(data.total, 'policy', 'policies') + (q ? ' matching “' + q + '”' : '') + '.'); }

				var shown = data.items;
				var repaint = shown.map(function (p) { return selectCard(p, sel, count); });

				var a;
				var prompted = false;
				for (;;) {
					var opts = [{ label: 'Select all shown', value: 'all' }];
					if (data.has_more) { opts.push({ label: 'Show more', value: 'more' }); }
					opts.push({ label: 'Search again', value: 'again' }, { label: 'Done', value: 'done', primary: true });

					a = await askChoice(prompted ? null : 'Tap Select on each policy you want, then tap Done.', opts, { actions: [{ label: 'Main menu', value: 'menu' }] });
					prompted = true;
					if (a.chip !== 'all') { break; }

					shown.forEach(function (p) { if (count() < BULK_MAX) { sel[p.id] = p; } });
					repaint.forEach(function (paint) { paint(); });
				}

				if (a.chip === 'menu') { return null; }
				if (a.chip === 'done') { return chosen(); }
				if (a.chip === 'more') { page++; continue; }
				break;
			}
		}
	}

	async function bulkFlow() {
		var a = await askChoice('What would you like to do with several policies at once?', [
			{ label: 'Archive them', value: 'archive' },
			{ label: 'Restore archived ones', value: 'restore' },
			{ label: 'Make them Private', value: 'private' },
			{ label: 'Make them Public', value: 'public' },
			{ label: 'Move them to a category', value: 'move' }
		], { actions: [{ label: 'Main menu', value: 'menu' }] });
		if (a.chip === 'menu') { return backToMenu(); }

		var action = a.chip;
		var to = 0;
		if (action === 'move') {
			var c = await askChoice('Which category should they all go in? A policy has one category, so this replaces its current one.', await categoryChoices(), {
				filterable: true, list: true, actions: [{ label: 'Cancel', value: 'cancel' }]
			});
			if (c.chip === 'cancel') { return backToMenu('Okay, nothing was changed.'); }
			to = Number(c.chip);
		}

		var picked = await pickMany(action === 'restore');
		if (!picked) { return backToMenu(); }
		if (!picked.length) { return backToMenu('You did not select any policy, so nothing was changed.'); }

		say(['You selected ', { b: countOf(picked.length, 'policy', 'policies') }, ':']);
		var rows = picked.slice(0, 10).map(function (p) { return [p.title, p.category || 'No category']; });
		if (picked.length > 10) { rows.push(['…', 'and ' + (picked.length - 10) + ' more']); }
		summaryCard(rows);

		var n = countOf(picked.length, 'policy', 'policies');
		var question = {
			archive: 'Archive ' + n + '? They will disappear from the policy lists, and you can restore them later.',
			restore: 'Restore ' + n + ' so they show in the policy lists again?',
			private: 'Make ' + n + ' Private? They will be hidden from regular users.',
			public: 'Make ' + n + ' Public? Everyone will be able to see them.',
			move: 'Move ' + n + ' to ' + catName(to) + '?'
		}[action];
		var ok = await askChoice(question, [{ label: 'Yes, do it', value: 'yes', primary: true }, { label: 'No, cancel', value: 'no' }]);
		if (ok.chip === 'no') { return backToMenu('Okay, nothing was changed.'); }

		var res = await call('bulk', { action: action, ids: picked.map(function (p) { return p.id; }).join(','), to: to || '' });
		if (!res.ok) {
			reportFailure(res, 'complete the bulk action');
			return backToMenu();
		}
		say(res.message, 'success');
		if (res.done) { say('Made a mistake? You can undo this for 24 hours from "Revert / undo a mistake".', 'hint'); }
		return backToMenu();
	}

	//------------------------------------------------------------------------
	// Export to CSV
	//------------------------------------------------------------------------

	function csvCell(v) {
		v = v == null ? '' : String(v);
		if (/^[=+\-@\t\r]/.test(v)) { v = '\'' + v; }   // stops a spreadsheet running the text as a formula
		return '"' + v.replace(/"/g, '""') + '"';
	}

	/** Saves the rows as a CSV file (with a byte-order mark so Excel reads accents correctly). Returns the file name. */
	function downloadCsv(header, rows) {
		var text = [header].concat(rows).map(function (r) { return r.map(csvCell).join(','); }).join('\r\n');
		var blob = new Blob(['﻿' + text], { type: 'text/csv;charset=utf-8' });
		var d = new Date();
		var name = 'policies-' + d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + '.csv';
		var a = el('a');
		a.href = URL.createObjectURL(blob);
		a.download = name;
		root.appendChild(a);
		a.click();
		root.removeChild(a);
		setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
		return name;
	}

	async function exportFlow() {
		var r = await askChoice('Which policies should go in the file?', [
			{ label: 'Active policies', value: 'active', primary: true },
			{ label: 'Archived policies', value: 'archived' },
			{ label: 'Everything', value: 'all' }
		], { actions: [{ label: 'Main menu', value: 'menu' }] });
		if (r.chip === 'menu') { return backToMenu(); }

		var res = await call('export', { scope: r.chip });
		if (!res.ok) {
			reportFailure(res, 'export the policies');
			return backToMenu();
		}
		if (!res.rows.length) { return backToMenu('There are no policies to export.'); }

		var name = downloadCsv(res.header, res.rows);
		say(['Done! ', { b: countOf(res.rows.length, 'policy', 'policies') }, ' saved to ', { b: name }, '. Look in your Downloads folder.'], 'success');
		return backToMenu();
	}

	//------------------------------------------------------------------------
	// Searches that found nothing
	//------------------------------------------------------------------------

	async function gapsFlow() {
		var res = await call('gaps');
		if (!res.ok) {
			reportFailure(res, 'look up the searches');
			return backToMenu();
		}
		if (!res.items.length) {
			return backToMenu('No search without results has been recorded yet. When a visitor searches for something and finds no policy, it will be listed here.');
		}

		say(['Visitors searched for these and found no policy. The most frequent come first:']);
		summaryCard(res.items.map(function (g) { return [g.q, countOf(g.n, 'search', 'searches') + ' · last ' + g.when]; }));
		if (res.total > res.items.length) { say('Showing ' + res.items.length + ' of ' + res.total + '.', 'hint'); }

		var r = await askChoice('What would you like to do?', [
			{ label: 'Add a policy', value: 'add', primary: true },
			{ label: 'Clear this list', value: 'clear' },
			{ label: 'Main menu', value: 'menu' }
		]);
		if (r.chip === 'add') { return addFlow(); }
		if (r.chip === 'clear') {
			var cleared = await call('gaps_clear');
			if (!cleared.ok) {
				reportFailure(cleared, 'clear the list');
				return backToMenu();
			}
			return backToMenu(cleared.message);
		}
		return backToMenu();
	}

	//------------------------------------------------------------------------
	// Categories: the sections of the menu on the left of the policy pages
	//------------------------------------------------------------------------

	var catRows = [];       // every category: {id, name (path), title, parent, count, children, visible}
	var catSections = [];   // main sections in the order the menu shows them: {id, title}

	function countOf(n, one, many) { return n + ' ' + (n === 1 ? one : many); }
	function catById(id) { return catRows.filter(function (c) { return c.id === Number(id); })[0]; }

	/** A category task. Every successful reply carries the fresh list, which also feeds the policy forms. */
	async function catCall(task, fields) {
		var res = await call(task, fields);
		if (res.ok && res.items) {
			catRows = res.items;
			catSections = res.sections || [];
			cfg.categories = res.items.map(function (c) { return { id: c.id, name: c.name }; });
		}
		return res;
	}

	/**
	 * Every category as a pick list in menu order: main sections as the menu shows them, each followed by
	 * its sub-categories, marked > (inside a section), >> (inside that) and so on.
	 */
	function catOptions(skip) {
		return catTree().filter(function (n) { return !skip || skip.indexOf(n.c.id) < 0; }).map(function (n) {
			return { label: (n.depth ? new Array(n.depth + 1).join('>') + ' ' : '') + n.c.title, echo: n.c.name, value: n.c.id, depth: n.depth };
		});
	}

	/** catOptions, loading the categories first if this chat has not yet. */
	async function categoryChoices(skip) {
		if (!catRows.length) { await catCall('cats'); }
		return catOptions(skip);
	}

	/** The category and everything inside it, by id. */
	function catBranch(id) {
		var c = catById(id);
		return catRows.filter(function (x) { return x.id === id || x.name.indexOf(c.name + ' > ') === 0; }).map(function (x) { return x.id; });
	}

	/** Categories as the menu lists them: main sections in menu order, then each one's children by name. */
	function catTree() {
		var out = [];
		function walk(parent, depth) {
			catRows.filter(function (c) { return c.parent === parent; })
				.sort(function (a, b) { return a.title.localeCompare(b.title); })
				.forEach(function (c) { out.push({ c: c, depth: depth }); walk(c.id, depth + 1); });
		}
		var tops = catRows.filter(function (c) { return c.parent === 0; });
		var ordered = catSections.map(function (s) { return catById(s.id); }).filter(Boolean);
		tops.forEach(function (t) { if (ordered.indexOf(t) < 0) { ordered.push(t); } });
		ordered.forEach(function (c) { out.push({ c: c, depth: 0 }); walk(c.id, 1); });
		return out;
	}

	function treeRow(kind, depth, text) {
		var row = el('div', 'hrcb-tree-row hrcb-tree-' + kind, text);
		row.style.setProperty('--depth', depth);
		return row;
	}

	/** The whole menu as visitors see it: one card per main section, with its policies and sub-categories. */
	async function showCategoryTree() {
		var res = await catCall('cats', { policies: 1 });
		if (!res.ok) {
			reportFailure(res, 'load the categories');
			return;
		}

		var byCat = {};
		(res.policies || []).forEach(function (p) {
			p.cats.forEach(function (id) { (byCat[id] = byCat[id] || []).push(p); });
		});
		function policyRows(card, c, depth) {
			(byCat[c.id] || []).forEach(function (p) { card.appendChild(treeRow('pol', depth, '• ' + p.title + (p.private ? ' (private)' : ''))); });
		}

		say('This is the whole menu, the way visitors see it: each section with its policies, and its sub-categories indented below. Archived policies are not shown.');
		var card = null;
		catTree().forEach(function (n) {
			if (n.depth === 0) {
				var total = catBranch(n.c.id).reduce(function (sum, id) { return sum + (byCat[id] || []).length; }, 0);
				card = el('div', 'hrcb-card');
				card.appendChild(el('div', 'hrcb-card-title', n.c.title));
				card.appendChild(el('div', 'hrcb-card-meta', countOf(total, 'policy', 'policies') + (n.c.visible ? '' : ' · not in the menu yet')));
				append(card);
			} else {
				card.appendChild(treeRow('cat', n.depth, n.c.title));
			}
			policyRows(card, n.c, n.depth + 1);
		});

		var loose = (res.policies || []).filter(function (p) { return !p.cats.some(function (id) { return catById(id); }); });
		if (loose.length) {
			card = el('div', 'hrcb-card');
			card.appendChild(el('div', 'hrcb-card-title', 'Not in any category'));
			card.appendChild(el('div', 'hrcb-card-meta', 'These ' + countOf(loose.length, 'policy', 'policies') + ' do not appear in the menu. Edit a policy to give it a category.'));
			loose.forEach(function (p) { card.appendChild(treeRow('pol', 1, '• ' + p.title)); });
			append(card);
		}
	}

	async function categoriesFlow(again) {
		var res = await catCall('cats');
		if (!res.ok) {
			reportFailure(res, 'load the categories');
			return backToMenu();
		}

		say(again ? 'What would you like to do next with the categories?' : 'Categories are the sections of the menu on the left of the policy pages. What would you like to do?');
		var r = await askChoice(null, [
			{ label: 'Add a category', value: 'add' },
			{ label: 'Manage a category', value: 'manage' },
			{ label: 'See all categories', value: 'tree' },
			{ label: 'Main menu', value: 'menu' }
		]);
		if (r.chip === 'menu') { return backToMenu(); }
		if (r.chip === 'add') { return addCategoryFlow(); }
		if (r.chip === 'tree') {
			await showCategoryTree();
			return categoriesFlow(true);
		}

		var c = await askChoice('Which category?', catOptions(), { filterable: true, list: true, actions: [{ label: 'Back', value: 'back' }] });
		return c.chip === 'back' ? categoriesFlow(true) : categoryFlow(Number(c.chip));
	}

	/** parentId: add inside that category; leave out to ask where it goes. */
	async function addCategoryFlow(parentId) {
		var name = await askText(
			parentId ? ['What should the new sub-category inside ', { b: catById(parentId).title }, ' be called?'] : 'What should the new category be called?',
			{
				placeholder: 'Category name',
				chips: [{ label: 'Cancel', value: 'cancel' }],
				validate: function (v) { return v.length >= 2 ? { value: v } : { error: 'The name must be at least two characters long.' }; }
			}
		);
		if (name.chip === 'cancel') { return categoriesFlow(true); }
		var title = name.value;

		var parent = parentId || 0;
		if (parentId === undefined) {
			var w = await askChoice('Where should it go?', [{ label: 'Main section (top level of the menu)', value: 'top' }].concat(catOptions()), {
				filterable: true,
				list: true,
				actions: [{ label: 'Cancel', value: 'cancel' }]
			});
			if (w.chip === 'cancel') { return categoriesFlow(true); }
			parent = w.chip === 'top' ? 0 : Number(w.chip);
		}

		for (;;) {
			say('I will add this category:');
			summaryCard([['Name', title], ['Where', parent ? 'Inside ' + catById(parent).name : 'Main section of the menu']]);
			var ok = await askChoice('Add it?', [{ label: 'Yes, add it', value: 'yes', primary: true }, { label: 'Cancel', value: 'cancel' }]);
			if (ok.chip === 'cancel') { return categoriesFlow(true); }

			var res = await catCall('cat_add', { name: title, parent: parent });
			if (res.ok) {
				say(['Done! ', { b: title }, ' has been added' + (parent ? '.' : ' and now shows in the menu.')], 'success');
				var next = await askChoice('What next?', [
					{ label: 'Add a policy to it', value: 'policy', primary: true },
					{ label: 'Add a sub-category inside it', value: 'sub' },
					{ label: 'Add another category', value: 'again' },
					{ label: 'Main menu', value: 'menu' }
				]);
				if (next.chip === 'policy') { return addFlow({ cat: res.id }); }
				if (next.chip === 'sub') { return addCategoryFlow(res.id); }
				return next.chip === 'again' ? addCategoryFlow() : backToMenu();
			}

			reportFailure(res, 'add the category');
			var retry = await askChoice('What would you like to do?', [{ label: 'Try a different name', value: 'retry', primary: true }, { label: 'Cancel', value: 'cancel' }]);
			if (retry.chip === 'cancel') { return categoriesFlow(true); }
			title = (await askText('What should it be called?', {
				placeholder: 'Category name',
				validate: function (v) { return v.length >= 2 ? { value: v } : { error: 'The name must be at least two characters long.' }; }
			})).value;
		}
	}

	async function listCategoryPolicies(id) {
		var token = {};
		for (var page = 1; ; page++) {
			var data = await call('search', { cat: id, page: page });
			if (!data.ok) {
				reportFailure(data, 'list the policies');
				return;
			}
			if (!data.items.length) {
				say(page === 1 ? 'There are no policies directly in this category.' : 'That is all of them.');
				return;
			}
			if (page === 1) { say('Found ' + countOf(data.total, 'policy', 'policies') + ' in ' + catById(id).title + '. Policies in its sub-categories are listed under those.'); }
			data.items.forEach(function (p) { policyCard(p, cardActions('search', p, token)); });
			if (!data.has_more) { return; }
			var more = await askChoice('What next?', [{ label: 'Show more', value: 'more' }, { label: 'Back', value: 'back' }]);
			if (more.chip !== 'more') { return; }
		}
	}

	/** Everything you can do with one category. Loops, so several changes can be made in a row. */
	async function categoryFlow(id) {
		for (;;) {
			var c = catById(id);
			if (!c) { return categoriesFlow(true); }
			var parent = c.parent ? catById(c.parent) : null;

			summaryCard([
				['Category', c.title],
				['Where', parent ? 'Inside ' + parent.name : 'Main section of the menu'],
				['Policies', String(c.count)],
				['Sub-categories', String(c.children)]
			]);
			if (!c.visible) { say('This main section is not showing in the menu yet. Choose "Show in menu" to fix that.', 'error'); }

			var options = [
				{ label: 'Add a policy here', value: 'policy' },
				{ label: 'See its policies', value: 'list' },
				{ label: 'Add a sub-category', value: 'sub' },
				{ label: 'Rename', value: 'rename' },
				{ label: 'Move', value: 'move' }
			];
			if (!c.parent) { options.push({ label: c.visible ? 'Change menu position' : 'Show in menu', value: 'order' }); }
			options.push({ label: 'Delete', value: 'delete' });

			var r = await askChoice('What would you like to do with it?', options, { actions: [{ label: 'Back', value: 'back' }] });
			if (r.chip === 'back') { return categoriesFlow(true); }
			if (r.chip === 'policy') { return addFlow({ cat: id }); }
			if (r.chip === 'sub') { return addCategoryFlow(id); }
			if (r.chip === 'list') { await listCategoryPolicies(id); continue; }

			if (r.chip === 'rename') {
				var n = await askText(['Current name: ', { b: c.title }, '\nWhat should it be called?'], {
					placeholder: 'Category name',
					chips: [CHIP.keep],
					validate: function (v) { return v.length >= 2 ? { value: v } : { error: 'The name must be at least two characters long.' }; }
				});
				if (n.chip || n.value === c.title) { continue; }
				var rn = await catCall('cat_edit', { id: id, name: n.value });
				if (!rn.ok) { reportFailure(rn, 'rename the category'); } else { say(['Done! It is now called ', { b: n.value }, '. The policies inside it moved along with it.'], 'success'); }
				continue;
			}

			if (r.chip === 'move') {
				var branch = catBranch(id);
				var targets = catOptions(branch.concat(c.parent ? [c.parent] : []));
				if (c.parent) { targets.unshift({ label: 'Main section (top level of the menu)', value: 'top' }); }
				if (!targets.length) { say('There is nowhere else to move it.'); continue; }
				var t = await askChoice(['Move ', { b: c.title }, ' under which category?'], targets, { filterable: true, list: true, actions: [{ label: 'Cancel', value: 'cancel' }] });
				if (t.chip === 'cancel') { continue; }
				var dest = t.chip === 'top' ? 0 : Number(t.chip);
				var okm = await askChoice(['Move ', { b: c.title }, ' to ', { b: dest ? catById(dest).name : 'the main sections' }, '? Its policies and sub-categories move with it.'], [
					{ label: 'Yes, move it', value: 'yes', primary: true }, { label: 'No', value: 'no' }
				]);
				if (okm.chip === 'no') { continue; }
				var mv = await catCall('cat_edit', { id: id, parent: dest });
				if (!mv.ok) { reportFailure(mv, 'move the category'); } else { say(['Done! ', { b: c.title }, ' has been moved.'], 'success'); }
				continue;
			}

			if (r.chip === 'order') {
				var after = -1;
				if (c.visible) {
					var spots = [{ label: 'First', value: 'first' }]
						.concat(catSections.filter(function (s) { return s.id !== id; }).map(function (s) { return { label: 'After ' + s.title, value: s.id }; }))
						.concat([{ label: 'Last', value: 'last' }]);
					var p = await askChoice('Where in the menu should ' + c.title + ' go?', spots, { actions: [{ label: 'Cancel', value: 'cancel' }] });
					if (p.chip === 'cancel') { continue; }
					after = p.chip === 'first' ? 0 : p.chip === 'last' ? -1 : Number(p.chip);
				}
				var od = await catCall('cat_order', { id: id, after: after });
				if (!od.ok) { reportFailure(od, 'change the menu position'); } else { say('Done! The menu order has been updated.', 'success'); }
				continue;
			}

			// delete
			if (c.children) {
				say(['I cannot delete ', { b: c.title }, ' yet: it still has ' + countOf(c.children, 'sub-category', 'sub-categories') + ' inside it. Move or delete those first.'], 'error');
				continue;
			}
			if (c.count) {
				say([{ b: c.title }, ' still has ' + countOf(c.count, 'policy', 'policies') + '. Deleting a category with policies would take them out of the menu, so they have to go to another category first.']);
				var mp = await askChoice('Move them to another category?', [{ label: 'Yes, choose a category', value: 'yes', primary: true }, { label: 'No, leave it', value: 'no' }]);
				if (mp.chip === 'no') { continue; }
				var tg = await askChoice('Move its policies to which category?', catOptions([id]), { filterable: true, list: true, actions: [{ label: 'Cancel', value: 'cancel' }] });
				if (tg.chip === 'cancel') { continue; }
				var toId = Number(tg.chip);
				var okd = await askChoice(['Move ' + countOf(c.count, 'policy', 'policies') + ' to ', { b: catById(toId).name }, ', then delete ', { b: c.title }, '? Deleting cannot be undone.'], [
					{ label: 'Yes, move and delete', value: 'yes', primary: true }, { label: 'No', value: 'no' }
				]);
				if (okd.chip === 'no') { continue; }
				var mvp = await catCall('cat_move', { id: id, to: toId });
				if (!mvp.ok) { reportFailure(mvp, 'move the policies'); continue; }
				say(mvp.message, 'success');
			} else {
				var okx = await askChoice(['Delete ', { b: c.title }, '? It is empty. Deleting cannot be undone.'], [
					{ label: 'Yes, delete it', value: 'yes', primary: true }, { label: 'No, keep it', value: 'no' }
				]);
				if (okx.chip === 'no') { continue; }
			}

			var del = await catCall('cat_delete', { id: id });
			if (!del.ok) {
				reportFailure(del, 'delete "' + c.title + '"');
				continue;
			}
			say(del.message, 'success');
			return categoriesFlow(true);
		}
	}

	//------------------------------------------------------------------------
	// Runner and wiring
	//------------------------------------------------------------------------

	function run(fn, arg) {
		cancelPending();
		var id = ++flowId;
		Promise.resolve().then(function () { return fn(arg); }).catch(function (err) {
			if (err === CANCEL || id !== flowId) { return; }
			if (window.console) { console.error(err); }
			say('Sorry, something went wrong on my side. Let\'s start again.', 'error');
			run(menuFlow);
		});
	}

	function startChat() {
		started = true;
		newChat();
		var name = cfg.userName ? ' ' + cfg.userName : '';
		run(menuFlow, cfg.canManage
			? 'Hi' + name + '! I\'m your Policy Assistant. What would you like to do today?'
			: 'Hi' + name + '! I\'m the Policy Assistant. Ask me about any policy in your own words, for example "how do I apply for maternity leave?" or "WFH rules".');
	}

	/** Ends the chat on screen: it is saved to the history, whatever it was doing is cancelled, and the window is emptied. */
	function endChat() {
		flowId++;            // anything still waiting for the server is dropped
		cancelPending();
		flushSave();
		chat = null;
		started = false;
		viewing = false;
		root.classList.remove('is-history', 'is-view');
		log.textContent = '';
		input.value = '';
		autosize();
		input.placeholder = 'Type a message';
		setBusy(false);
	}

	/** A fresh chat window; the one before stays in the history. */
	function newChatWindow() {
		endChat();
		startChat();
		input.focus();
	}

	function open() {
		root.classList.add('is-open');
		launcher.setAttribute('aria-expanded', 'true');
		if (!started && !viewing) { startChat(); }
		input.focus();
	}

	/** Hides the window but keeps the chat as it is (Escape). */
	function hide() {
		root.classList.remove('is-open');
		launcher.setAttribute('aria-expanded', 'false');
		launcher.focus();
	}

	/** The X button: ends the chat. Opening the assistant again starts a new one. */
	function close() {
		endChat();
		hide();
	}

	function hideHistory() { root.classList.remove('is-history'); }

	function showHistory() {
		if (chat) { flushSave(); }
		historyEl.textContent = '';
		historyEl.appendChild(el('div', 'hrcb-history-head', 'Chat history'));
		historyEl.appendChild(el('div', 'hrcb-history-note', 'Your chats from the last ' + HISTORY_DAYS + ' days, newest first. They are kept in this browser only.'));

		var fresh = el('button', 'hrcb-btn hrcb-btn-primary hrcb-history-new', 'New chat');
		fresh.type = 'button';
		fresh.addEventListener('click', newChatWindow);
		historyEl.appendChild(fresh);

		var list = loadChats().sort(function (a, b) { return b.t1 - a.t1; });
		if (!list.length) {
			historyEl.appendChild(el('div', 'hrcb-history-note', 'No chats yet. A chat appears here once you have written something in it.'));
		}
		list.forEach(function (c) {
			var item = el('button', 'hrcb-history-item');
			item.type = 'button';
			item.appendChild(el('span', 'hrcb-history-title', c.title));
			item.appendChild(el('span', 'hrcb-history-when', whenLabel(c.t1)));
			item.addEventListener('click', function () { viewChat(c); });
			historyEl.appendChild(item);
		});

		if (list.length) {
			var clear = el('button', 'hrcb-btn hrcb-history-clear', 'Clear history');
			clear.type = 'button';
			clear.addEventListener('click', function () {
				if (clear.dataset.sure !== '1') {
					clear.dataset.sure = '1';
					clear.textContent = 'Tap again to delete all saved chats';
					return;
				}
				try { window.localStorage.removeItem(STORE_KEY); } catch (e) { /* ignore */ }
				showHistory();
			});
			historyEl.appendChild(clear);
		}
		root.classList.add('is-history');
	}

	/** Opens an old chat as a read-only transcript. The chat that was on screen is ended (and kept in the history). */
	function viewChat(c) {
		endChat();
		viewing = true;
		root.classList.add('is-view');

		var bar = el('div', 'hrcb-viewbar');
		bar.appendChild(el('span', 'hrcb-viewbar-text', 'Past chat from ' + whenLabel(c.t0) + '. This is a read-only copy.'));
		var all = el('button', 'hrcb-btn', 'All chats');
		all.type = 'button';
		all.addEventListener('click', showHistory);
		var fresh = el('button', 'hrcb-btn hrcb-btn-primary', 'New chat');
		fresh.type = 'button';
		fresh.addEventListener('click', newChatWindow);
		bar.appendChild(all);
		bar.appendChild(fresh);
		log.appendChild(bar);

		var parser = new DOMParser();
		c.msgs.forEach(function (html) {
			var doc = parser.parseFromString(html, 'text/html');
			var node = doc.body.firstChild ? rebuild(doc.body.firstChild) : null;
			if (node) { log.appendChild(node); }
		});
		log.scrollTop = 0;
	}

	launcher.addEventListener('click', open);
	root.querySelector('[data-act="close"]').addEventListener('click', close);
	historyBtn.addEventListener('click', function () {
		if (root.classList.contains('is-history')) { hideHistory(); } else { showHistory(); }
	});
	menuBtn.addEventListener('click', function () {
		if (busy) { return; }
		hideHistory();
		if (viewing) { newChatWindow(); } else { run(menuFlow); }
	});
	root.addEventListener('keydown', function (e) { if (e.key === 'Escape' && root.classList.contains('is-open')) { hide(); } });
	window.addEventListener('pagehide', flushSave);

	form.addEventListener('submit', function (e) { e.preventDefault(); handleInput(); });
	input.addEventListener('input', autosize);
	input.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
			e.preventDefault();
			handleInput();
		}
	});
})();
