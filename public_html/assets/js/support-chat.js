// ============================================
// Support Chat Widget — rule-based assistant
// ============================================

(function () {
  'use strict';

  // ============================================
  // Config — edit these
  // ============================================
  const CONFIG = {
    adminEmail: 'admin@dammc.edu.ph',
    adminPhone: '(062) 991-1234',
    adminHours: 'Mon–Fri, 8:00 AM – 5:00 PM',
    office: "Registrar's Office, Main Building",
    botName: 'DAMMC Support',
    botAvatar: 'D',
  };

  // ============================================
  // Knowledge base — keyword → reply
  // Order matters: first match wins
  // ============================================
  const RULES = [
    {
      keys: ['forgot password', 'forgot my password', 'reset password', 'reset my password'],
      reply: () => `No problem! You can reset your password yourself:
        <ol class="list-decimal pl-4 mt-1 space-y-0.5">
          <li>Go to the <a href="/forgot-password" class="text-[#0F5E3D] underline underline-offset-2">Forgot Password</a> page.</li>
          <li>Enter your registered email.</li>
          <li>Check your inbox for the reset link (valid for 1 hour).</li>
        </ol>
        If you don't receive it within 5 minutes, check spam or contact the admin.`,
    },
    {
      keys: ['locked', 'deactivated', 'inactive', 'disabled', 'suspended'],
      reply: () => `If your account is deactivated, you'll see a message after signing in.
        Only an administrator can reactivate it. Please contact:
        <ul class="list-disc pl-4 mt-1 space-y-0.5">
          <li>Email: <a href="mailto:${CONFIG.adminEmail}" class="text-[#0F5E3D] underline">${CONFIG.adminEmail}</a></li>
          <li>Phone: ${CONFIG.adminPhone}</li>
          <li>Visit: ${CONFIG.office}</li>
        </ul>`,
    },
    {
      keys: ['username', 'user name', 'login name'],
      reply: () => `Your username follows the pattern <code class="px-1 py-0.5 bg-slate-100 rounded text-[11px]">firstname.lastname</code> in lowercase.
        <br><br>For example: <code class="px-1 py-0.5 bg-slate-100 rounded text-[11px]">juan.delacruz</code>.
        <br><br>If you're not sure what your username is, contact the admin: <a href="mailto:${CONFIG.adminEmail}" class="text-[#0F5E3D] underline">${CONFIG.adminEmail}</a>.`,
    },
    {
      keys: ['wrong password', "password doesn't work", 'password not working', 'invalid password'],
      reply: () => `A few things to check first:
        <ol class="list-decimal pl-4 mt-1 space-y-0.5">
          <li>Is Caps Lock on?</li>
          <li>Any trailing spaces? Try retyping.</li>
          <li>Did you recently change it? Old passwords stop working immediately.</li>
        </ol>
        Still stuck? Try <a href="/forgot-password" class="text-[#0F5E3D] underline">resetting it</a>.`,
    },
    {
      keys: ['no account', 'new employee', 'new user', "don't have an account", 'no login'],
      reply: () => `New accounts are created by the administrator.
        Once your employee record is set up in HR, an account is created for you and a welcome email is sent with your username and temporary password.
        <br><br>If you've been with DAMMC for a while and still have no account, contact: <a href="mailto:${CONFIG.adminEmail}" class="text-[#0F5E3D] underline">${CONFIG.adminEmail}</a>.`,
    },
    {
      keys: ['contact', 'human', 'admin', 'talk to someone', 'reach'],
      reply: () => `You can reach the administrator here:
        <ul class="list-disc pl-4 mt-1 space-y-0.5">
          <li>Email: <a href="mailto:${CONFIG.adminEmail}" class="text-[#0F5E3D] underline">${CONFIG.adminEmail}</a></li>
          <li>Phone: ${CONFIG.adminPhone}</li>
          <li>Office: ${CONFIG.office}</li>
          <li>Hours: ${CONFIG.adminHours}</li>
        </ul>`,
    },
    {
      keys: ['leave', 'file leave', 'leave request', 'how to file'],
      reply: () => `To file a leave:
        <ol class="list-decimal pl-4 mt-1 space-y-0.5">
          <li>Sign in and open <strong>My Leaves</strong>.</li>
          <li>Click <strong>File Leave</strong>.</li>
          <li>Choose the leave type, dates, and reason.</li>
          <li>Submit — HR and your department head will review it.</li>
        </ol>
        Part-time employees are not eligible to file leave.`,
    },
    {
      keys: ['credits', 'leave credits', 'how many credits', 'balance'],
      reply: () => `Your remaining leave credits are shown on your <strong>Dashboard</strong> and on the <strong>My Leaves</strong> page.
        <br><br>Full-time employees start with 15 credits per year. Vacation and sick leaves each have their own 7.5-day cap.
        <br><br>Part-time employees do not accrue leave credits.`,
    },
    {
      keys: ['error', 'not working', 'broken', 'crash', 'problem'],
      reply: () => `Sorry about that. Try these first:
        <ol class="list-decimal pl-4 mt-1 space-y-0.5">
          <li>Refresh the page (Ctrl+R / Cmd+R).</li>
          <li>Clear your browser cache.</li>
          <li>Try a different browser.</li>
        </ol>
        If the problem persists, contact: <a href="mailto:${CONFIG.adminEmail}" class="text-[#0F5E3D] underline">${CONFIG.adminEmail}</a>.`,
    },
    {
      keys: ['hours', 'open', 'available', 'when'],
      reply: () => `Support hours: <strong>${CONFIG.adminHours}</strong>.
        <br><br>For urgent login issues outside these hours, email <a href="mailto:${CONFIG.adminEmail}" class="text-[#0F5E3D] underline">${CONFIG.adminEmail}</a> and we'll respond the next business day.`,
    },
  ];

  const FALLBACK_REPLY = () => `I'm not sure I understood that. Here are things I can help with:
    <ul class="list-disc pl-4 mt-1 space-y-0.5">
      <li>Forgotten password</li>
      <li>Locked or deactivated account</li>
      <li>Username issues</li>
      <li>Filing a leave</li>
      <li>Leave credits</li>
      <li>Contacting the admin</li>
    </ul>
    Or type <strong>contact</strong> to reach a human.`;

  // ============================================
  // Quick replies shown as chips
  // ============================================
  const QUICK_REPLIES = [
    { label: 'Forgot password', value: 'forgot password' },
    { label: 'Account locked', value: 'locked' },
    { label: 'Username issues', value: 'username' },
    { label: 'File a leave', value: 'how to file a leave' },
    { label: 'Talk to admin', value: 'contact' },
  ];

  // ============================================
  // Build the widget DOM
  // ============================================
  function buildWidget() {
    const html = `
      <!-- Chat button -->
      <button id="scOpenBtn" title="Chat with support"
        class="fixed bottom-5 right-5 w-14 h-14 rounded-full bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white shadow-lg hover:shadow-xl transition-all flex items-center justify-center z-[60]">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        </svg>
        <span id="scUnread" class="hidden absolute -top-1 -right-1 w-4 h-4 rounded-full bg-red-500 text-white text-[10px] font-semibold flex items-center justify-center">1</span>
      </button>

      <!-- Chat window -->
      <div id="scWindow"
        class="fixed bottom-24 right-5 w-[360px] max-w-[calc(100vw-2.5rem)] h-[520px] max-h-[calc(100vh-8rem)] bg-white rounded-2xl shadow-2xl border border-gray-200 flex-col overflow-hidden z-[60] hidden">

        <!-- Header -->
        <div class="bg-[#0F5E3D] text-white px-4 py-3.5 flex items-center gap-3 shrink-0">
          <div class="w-9 h-9 rounded-full bg-white/15 flex items-center justify-center text-white font-semibold">
            ${CONFIG.botAvatar}
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold leading-tight">${CONFIG.botName}</p>
            <p class="text-[11px] text-white/70 flex items-center gap-1">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-300"></span>
              Online · Replies instantly
            </p>
          </div>
          <button id="scCloseBtn" title="Close" class="text-white/70 hover:text-white transition p-1">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>

        <!-- Messages -->
        <div id="scMessages" class="flex-1 overflow-y-auto px-4 py-4 space-y-3 bg-[#F9FAFB]"></div>

        <!-- Quick replies -->
        <div id="scQuickReplies" class="px-3 py-2 flex flex-wrap gap-1.5 border-t border-gray-100 bg-white shrink-0"></div>

        <!-- Composer -->
        <form id="scForm" class="border-t border-gray-100 p-3 flex items-center gap-2 bg-white shrink-0">
          <input id="scInput" type="text" autocomplete="off" placeholder="Type a message…"
            class="flex-1 px-3 py-2 border border-gray-200 rounded-full text-sm text-[#2C3E50] focus:outline-none focus:ring-2 focus:ring-[#0F5E3D] focus:border-transparent transition">
          <button type="submit" title="Send"
            class="w-9 h-9 rounded-full bg-[#0F5E3D] hover:bg-[#0a4a2f] text-white flex items-center justify-center transition shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="22" y1="2" x2="11" y2="13"></line>
              <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
            </svg>
          </button>
        </form>
      </div>
    `;
    const wrap = document.createElement('div');
    wrap.innerHTML = html;
    document.body.appendChild(wrap);
  }

  // ============================================
  // State
  // ============================================
  let isOpen = false;
  let greetingShown = false;

  // ============================================
  // Render helpers
  // ============================================
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[c]);
  }

  function addMessage(text, sender) {
    const box = document.getElementById('scMessages');
    if (!box) return;

    const isBot = sender === 'bot';
    const el = document.createElement('div');
    el.className = `flex ${isBot ? 'justify-start' : 'justify-end'}`;
    el.innerHTML = isBot
      ? `<div class="max-w-[85%] bg-white border border-gray-200 text-[#2C3E50] text-sm px-3 py-2 rounded-2xl rounded-tl-sm shadow-sm">${text}</div>`
      : `<div class="max-w-[85%] bg-[#0F5E3D] text-white text-sm px-3 py-2 rounded-2xl rounded-tr-sm">${escapeHtml(text)}</div>`;

    box.appendChild(el);
    box.scrollTop = box.scrollHeight;
  }

  function showTyping() {
    const box = document.getElementById('scMessages');
    if (!box) return;
    const el = document.createElement('div');
    el.id = 'scTyping';
    el.className = 'flex justify-start';
    el.innerHTML = `
      <div class="bg-white border border-gray-200 px-3 py-2.5 rounded-2xl rounded-tl-sm shadow-sm flex items-center gap-1">
        <span class="w-1.5 h-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay:0ms"></span>
        <span class="w-1.5 h-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay:150ms"></span>
        <span class="w-1.5 h-1.5 rounded-full bg-gray-400 animate-bounce" style="animation-delay:300ms"></span>
      </div>
    `;
    box.appendChild(el);
    box.scrollTop = box.scrollHeight;
  }

  function hideTyping() {
    document.getElementById('scTyping')?.remove();
  }

  function botReply(htmlContent) {
    showTyping();
    const delay = 500 + Math.min(htmlContent.length * 4, 800);
    setTimeout(() => {
      hideTyping();
      addMessage(htmlContent, 'bot');
    }, delay);
  }

  // ============================================
  // Rule matching
  // ============================================
  function findReply(userText) {
    const lower = userText.toLowerCase().trim();
    if (!lower) return FALLBACK_REPLY();

    for (const rule of RULES) {
      for (const key of rule.keys) {
        if (lower.includes(key)) {
          return rule.reply;
        }
      }
    }
    return FALLBACK_REPLY;
  }

  // ============================================
  // Quick replies
  // ============================================
  function renderQuickReplies() {
    const box = document.getElementById('scQuickReplies');
    if (!box) return;
    box.innerHTML = '';
    QUICK_REPLIES.forEach((q) => {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'px-2.5 py-1 rounded-full text-[11px] font-medium border border-gray-200 text-[#2C3E50]/80 bg-white hover:bg-[#F1FDF6] hover:border-[#0F5E3D]/30 transition';
      chip.textContent = q.label;
      chip.addEventListener('click', () => handleUserMessage(q.value));
      box.appendChild(chip);
    });
  }

  // ============================================
  // Handle send
  // ============================================
  function handleUserMessage(text) {
    const cleaned = String(text || '').trim();
    if (!cleaned) return;

    addMessage(cleaned, 'user');
    const replyFn = findReply(cleaned);
    botReply(replyFn());
  }

  // ============================================
  // Open / close
  // ============================================
  function openChat() {
    const w = document.getElementById('scWindow');
    const unread = document.getElementById('scUnread');
    if (!w) return;
    w.classList.remove('hidden');
    w.classList.add('flex');
    isOpen = true;
    unread?.classList.add('hidden');

    if (!greetingShown) {
      greetingShown = true;
      const hour = new Date().getHours();
      const greet = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
      setTimeout(() => {
        botReply(`${greet}! I'm DAMMC Support. How can I help you sign in today?
          <br><br>You can tap a suggestion below or type your question.`);
      }, 250);
    }

    setTimeout(() => document.getElementById('scInput')?.focus(), 100);
  }

  function closeChat() {
    const w = document.getElementById('scWindow');
    if (!w) return;
    w.classList.add('hidden');
    w.classList.remove('flex');
    isOpen = false;
  }

  // ============================================
  // Wire up
  // ============================================
  function init() {
    buildWidget();

    document.getElementById('scOpenBtn')?.addEventListener('click', () => {
      isOpen ? closeChat() : openChat();
    });
    document.getElementById('scCloseBtn')?.addEventListener('click', closeChat);

    document.getElementById('scForm')?.addEventListener('submit', (e) => {
      e.preventDefault();
      const input = document.getElementById('scInput');
      const text = input.value;
      input.value = '';
      handleUserMessage(text);
    });

    renderQuickReplies();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();