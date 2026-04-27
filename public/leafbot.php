<?php
require __DIR__ . '/../app/bootstrap.php';
$user = current_user();
$displayName = trim((string)($user['name'] ?? ''));
$firstName = $displayName !== '' ? preg_split('/\s+/', $displayName)[0] : '';
$locale = current_locale();
if ($locale === 'hi') {
    $greeting = $firstName !== '' ? 'Namaste, ' . $firstName : 'Namaste';
} elseif ($locale === 'or' || $locale === 'mr') {
    $greeting = $firstName !== '' ? 'Namaskar, ' . $firstName : 'Namaskar';
} elseif ($locale === 'pa') {
    $greeting = $firstName !== '' ? 'Sat Sri Akal, ' . $firstName : 'Sat Sri Akal';
} elseif ($locale === 'bn') {
    $greeting = $firstName !== '' ? 'Nomoskar, ' . $firstName : 'Nomoskar';
} elseif ($locale === 'ml') {
    $greeting = $firstName !== '' ? 'Namaskaram, ' . $firstName : 'Namaskaram';
} else {
    $greeting = $firstName !== '' ? 'Hi, ' . $firstName : 'Hi, there';
}

$leafbotState = [
    'storageSuffix' => $user ? 'user_' . (string)$user['id'] : 'guest',
    'csrf' => csrf_token(),
    'ready' => t('leafbot.status_ready'),
    'thinking' => t('leafbot.status_thinking'),
    'statusError' => t('leafbot.status_error'),
    'missingKey' => t('leafbot.status_missing_key'),
    'emptyState' => t('leafbot.empty_state'),
    'promptError' => t('leafbot.error_prompt'),
    'networkError' => t('leafbot.error_network'),
    'listening' => t('leafbot.status_listening'),
    'micUnsupported' => t('leafbot.status_mic_unsupported'),
    'micDenied' => t('leafbot.status_mic_denied'),
    'micError' => t('leafbot.status_mic_error'),
    'send' => t('leafbot.send'),
    'voice' => t('leafbot.voice'),
    'voiceStop' => t('leafbot.voice_stop'),
    'newChat' => t('leafbot.new_chat'),
    'historyTitle' => t('leafbot.history_title'),
    'historyEmpty' => t('leafbot.history_empty'),
    'historyUntitled' => t('leafbot.history_untitled'),
    'chatArchived' => t('leafbot.chat_archived'),
    'menuAria' => t('leafbot.menu_aria'),
    'menuMore' => t('leafbot.menu_more'),
    'menuShare' => t('leafbot.menu_share'),
    'menuRename' => t('leafbot.menu_rename'),
    'menuUnpin' => t('leafbot.menu_unpin'),
    'menuPin' => t('leafbot.menu_pin'),
    'menuUnarchive' => t('leafbot.menu_unarchive'),
    'menuArchive' => t('leafbot.menu_archive'),
    'menuDelete' => t('leafbot.menu_delete'),
    'shareSuccess' => t('leafbot.share_success'),
    'shareCopied' => t('leafbot.share_copied'),
    'shareFailed' => t('leafbot.share_failed'),
    'shareUnavailable' => t('leafbot.share_unavailable'),
    'shareOpenPrompt' => t('leafbot.share_open_prompt'),
    'shareCopyPrompt' => t('leafbot.share_copy_prompt'),
    'renamePrompt' => t('leafbot.rename_prompt'),
    'statusSharedOpened' => t('leafbot.status_shared_opened'),
    'statusSharedOpenFailed' => t('leafbot.status_shared_open_failed'),
    'sidebarShow' => t('leafbot.sidebar_show'),
    'sidebarHide' => t('leafbot.sidebar_hide'),
    'labelYou' => t('leafbot.label_you'),
    'recognitionLang' => ($locale === 'hi')
        ? 'hi-IN'
        : (($locale === 'or')
            ? 'or-IN'
            : (($locale === 'mr')
                ? 'mr-IN'
                : (($locale === 'pa')
                    ? 'pa-IN'
                    : (($locale === 'bn')
                        ? 'bn-IN'
                        : (($locale === 'ml') ? 'ml-IN' : 'en-IN'))))),
];
$title = t('leafbot.title');
require __DIR__ . '/../app/views/header.php';
?>

<section class="leafbot-shell" id="leafbotShell">
    <div class="container leafbot-hero">
        <div class="leafbot-watermark" aria-hidden="true">
            <span class="leafbot-watermark-main"></span>
            <span class="leafbot-watermark-leaf"></span>
        </div>

        <button class="leafbot-sidebar-toggle" type="button" id="leafbotSidebarToggle" aria-label="<?= e(t('leafbot.sidebar_hide')) ?>" title="<?= e(t('leafbot.sidebar_hide')) ?>">
            <span class="leafbot-sidebar-toggle-icon" aria-hidden="true"></span>
        </button>

        <aside class="leafbot-sidebar">
            <div class="leafbot-sidebar-top">
                <button class="leafbot-new-chat" type="button" id="leafbotNewChat">+ <?= e(t('leafbot.new_chat')) ?></button>
                <div class="leafbot-sidebar-title"><?= e(t('leafbot.history_title')) ?></div>
            </div>
            <div class="leafbot-chatlist-wrap">
                <div class="leafbot-chatlist" id="leafbotChatList">
                    <div class="leafbot-chatlist-empty" id="leafbotChatListEmpty"><?= e(t('leafbot.history_empty')) ?></div>
                </div>
            </div>
        </aside>

        <div class="leafbot-main">
            <div class="leafbot-copy">
                <div class="leafbot-kicker"><?= e(t('leafbot.kicker')) ?></div>
                <h1><?= e($greeting) ?></h1>
                <p class="leafbot-sub"><?= e(t('leafbot.subheading')) ?></p>
            </div>

            <div class="leafbot-console" role="region" aria-label="<?= e(t('leafbot.panel_label')) ?>">
                <div class="leafbot-console-head">
                    <strong><?= e(t('leafbot.panel_title')) ?></strong>
                    <span><?= e(t('leafbot.panel_hint')) ?></span>
                </div>

                <div class="leafbot-status" id="leafbotStatus"><?= e(t('leafbot.status_ready')) ?></div>
                <div class="leafbot-thread" id="leafbotThread" aria-live="polite">
                    <div class="leafbot-empty" id="leafbotEmpty"><?= e(t('leafbot.empty_state')) ?></div>
                </div>

                <div class="leafbot-composer">
                    <label class="sr-only" for="leafbotPrompt"><?= e(t('leafbot.input_label')) ?></label>
                    <textarea id="leafbotPrompt" class="leafbot-textarea" placeholder="<?= e(t('leafbot.placeholder')) ?>"></textarea>

                    <div class="leafbot-toolbar">
                        <button class="leafbot-mic" type="button" id="leafbotMic" aria-label="<?= e(t('leafbot.voice')) ?>" title="<?= e(t('leafbot.voice')) ?>">
                            <span class="leafbot-mic-icon" aria-hidden="true"></span>
                        </button>
                        <button class="leafbot-send" type="button" id="leafbotSend" aria-label="<?= e(t('leafbot.send')) ?>" title="<?= e(t('leafbot.send')) ?>">
                            <span class="leafbot-send-caret" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="leafbot-shortcuts" aria-label="<?= e(t('leafbot.shortcuts_label')) ?>">
                <a class="leafbot-chip" href="diagnose.php"><?= e(t('leafbot.chip_diagnose')) ?></a>
                <a class="leafbot-chip" href="weather.php"><?= e(t('leafbot.chip_weather')) ?></a>
                <a class="leafbot-chip" href="forum.php"><?= e(t('leafbot.chip_community')) ?></a>
                <a class="leafbot-chip" href="history.php"><?= e(t('leafbot.chip_history')) ?></a>
            </div>
        </div>
    </div>
</section>

<script>
const LEAFBOT = <?= json_encode($leafbotState, JSON_UNESCAPED_UNICODE) ?>;

const leafbotPromptEl = document.getElementById('leafbotPrompt');
const leafbotSendEl = document.getElementById('leafbotSend');
const leafbotMicEl = document.getElementById('leafbotMic');
const leafbotNewChatEl = document.getElementById('leafbotNewChat');
const leafbotSidebarToggleEl = document.getElementById('leafbotSidebarToggle');
const leafbotChatListEl = document.getElementById('leafbotChatList');
const leafbotChatListEmptyEl = document.getElementById('leafbotChatListEmpty');
const leafbotThreadEl = document.getElementById('leafbotThread');
const leafbotStatusEl = document.getElementById('leafbotStatus');
const leafbotEmptyEl = document.getElementById('leafbotEmpty');
const leafbotShellEl = document.getElementById('leafbotShell');

const LEAFBOT_STORAGE_KEY = `agrico_leafbot_conversations_${LEAFBOT.storageSuffix}`;
const LEAFBOT_SIDEBAR_KEY = `agrico_leafbot_sidebar_collapsed_${LEAFBOT.storageSuffix}`;
let leafbotHistory = [];
let leafbotConversations = [];
let leafbotActiveConversationId = null;
let leafbotOpenMenuId = null;
const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
let leafbotRecognition = null;
let leafbotListening = false;
let leafbotTranscriptBase = '';
let leafbotFollowBottom = true;

function leafbotSafeStorageRead() {
  try {
    const raw = localStorage.getItem(LEAFBOT_STORAGE_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed)
      ? parsed.map((conversation) => ({
          ...conversation,
          pinned: Boolean(conversation?.pinned),
          archived: Boolean(conversation?.archived),
          messages: Array.isArray(conversation?.messages) ? conversation.messages : [],
        }))
      : [];
  } catch (error) {
    return [];
  }
}

function leafbotSafeStorageWrite() {
  try {
    localStorage.setItem(LEAFBOT_STORAGE_KEY, JSON.stringify(leafbotConversations));
  } catch (error) {
    // Ignore storage failures gracefully.
  }
}

function leafbotBuildShareLink(conversation) {
  try {
    const payload = {
      title: conversation.title || LEAFBOT.historyUntitled,
      messages: Array.isArray(conversation.messages) ? conversation.messages : [],
    };
    return `${window.location.origin}${window.location.pathname}#share=${encodeURIComponent(JSON.stringify(payload))}`;
  } catch (error) {
    return window.location.href;
  }
}

function leafbotImportSharedConversation() {
  const hash = window.location.hash || '';
  if (!hash.startsWith('#share=')) return;

  try {
    const payload = JSON.parse(decodeURIComponent(hash.slice(7)));
    const fresh = leafbotCreateConversation(Array.isArray(payload?.messages) ? payload.messages : []);
    if (typeof payload?.title === 'string' && payload.title.trim() !== '') {
      fresh.title = payload.title.trim().slice(0, 60);
    }
    leafbotConversations.unshift(fresh);
    leafbotActiveConversationId = fresh.id;
    leafbotHistory = fresh.messages.slice();
    leafbotSafeStorageWrite();
    leafbotRenderThreadFromHistory();
    leafbotRenderConversationList();
    leafbotSetStatus(LEAFBOT.statusSharedOpened, false);
    history.replaceState(null, '', window.location.pathname);
  } catch (error) {
    leafbotSetStatus(LEAFBOT.statusSharedOpenFailed, true);
  }
}

function leafbotSidebarCollapsed() {
  try {
    return localStorage.getItem(LEAFBOT_SIDEBAR_KEY) === '1';
  } catch (error) {
    return false;
  }
}

function leafbotSetSidebarCollapsed(isCollapsed) {
  if (!leafbotShellEl || !leafbotSidebarToggleEl) return;
  leafbotShellEl.classList.toggle('leafbot-sidebar-collapsed', isCollapsed);
  leafbotSidebarToggleEl.setAttribute('aria-label', isCollapsed ? LEAFBOT.sidebarShow : LEAFBOT.sidebarHide);
  leafbotSidebarToggleEl.title = isCollapsed ? LEAFBOT.sidebarShow : LEAFBOT.sidebarHide;
  try {
    localStorage.setItem(LEAFBOT_SIDEBAR_KEY, isCollapsed ? '1' : '0');
  } catch (error) {
    // Ignore storage failures gracefully.
  }
}

function leafbotConversationTitle(messages) {
  const firstUser = messages.find((message) => message.role === 'user' && String(message.content || '').trim() !== '');
  const title = firstUser ? String(firstUser.content).trim() : '';
  return title ? title.slice(0, 36) : LEAFBOT.historyUntitled;
}

function leafbotCreateConversation(seedMessages = []) {
  const now = new Date().toISOString();
  return {
    id: `chat_${Date.now()}_${Math.random().toString(16).slice(2, 8)}`,
    title: leafbotConversationTitle(seedMessages),
    updatedAt: now,
    messages: seedMessages,
    pinned: false,
    archived: false,
  };
}

function leafbotFindConversationIndex(id) {
  return leafbotConversations.findIndex((conversation) => conversation.id === id);
}

function leafbotGetConversation(id) {
  return leafbotConversations.find((conversation) => conversation.id === id) || null;
}

function leafbotEnsureActiveConversation() {
  if (leafbotActiveConversationId && leafbotFindConversationIndex(leafbotActiveConversationId) !== -1) {
    return;
  }
  const fresh = leafbotCreateConversation();
  leafbotConversations.unshift(fresh);
  leafbotActiveConversationId = fresh.id;
  leafbotSafeStorageWrite();
}

function leafbotOrderedConversations() {
  return [...leafbotConversations].sort((a, b) => {
    if (a.pinned !== b.pinned) return a.pinned ? -1 : 1;
    if (a.archived !== b.archived) return a.archived ? 1 : -1;
    return new Date(b.updatedAt).getTime() - new Date(a.updatedAt).getTime();
  });
}

function leafbotRenderConversationList() {
  if (!leafbotChatListEl || !leafbotChatListEmptyEl) return;
  leafbotChatListEl.querySelectorAll('.leafbot-chatlist-row').forEach((item) => item.remove());
  leafbotChatListEmptyEl.hidden = leafbotConversations.length > 0;

  leafbotOrderedConversations().forEach((conversation) => {
    const row = document.createElement('div');
    row.className = 'leafbot-chatlist-row';
    if (conversation.id === leafbotActiveConversationId) {
      row.classList.add('is-active');
    }

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'leafbot-chatlist-item';
    if (conversation.id === leafbotActiveConversationId) {
      button.classList.add('is-active');
    }

    const title = document.createElement('strong');
    title.textContent = `${conversation.pinned ? '\uD83D\uDCCC ' : ''}${conversation.title || LEAFBOT.historyUntitled}`;

    const meta = document.createElement('span');
    const parts = [];
    if (conversation.archived) parts.push(LEAFBOT.chatArchived);
    parts.push(new Date(conversation.updatedAt).toLocaleString());
    meta.textContent = parts.join(' · ');

    button.appendChild(title);
    button.appendChild(meta);
    button.addEventListener('click', () => leafbotLoadConversation(conversation.id));

    const menuWrap = document.createElement('div');
    menuWrap.className = 'leafbot-chatlist-menuwrap';

    const menuButton = document.createElement('button');
    menuButton.type = 'button';
    menuButton.className = 'leafbot-chatlist-menubtn';
    menuButton.setAttribute('aria-label', `${LEAFBOT.menuAria} ${conversation.title || LEAFBOT.historyUntitled}`);
    menuButton.title = LEAFBOT.menuMore;
    menuButton.textContent = '...';
    menuButton.addEventListener('click', (event) => {
      event.stopPropagation();
      leafbotOpenMenuId = leafbotOpenMenuId === conversation.id ? null : conversation.id;
      leafbotRenderConversationList();
    });

    const menu = document.createElement('div');
    menu.className = 'leafbot-chatlist-menu';
    if (leafbotOpenMenuId === conversation.id) {
      menu.classList.add('is-open');
    }

    [
      { label: LEAFBOT.menuShare, action: () => leafbotShareConversation(conversation.id) },
      { label: LEAFBOT.menuRename, action: () => leafbotRenameConversation(conversation.id) },
      { label: conversation.pinned ? LEAFBOT.menuUnpin : LEAFBOT.menuPin, action: () => leafbotTogglePinnedConversation(conversation.id) },
      { label: conversation.archived ? LEAFBOT.menuUnarchive : LEAFBOT.menuArchive, action: () => leafbotToggleArchiveConversation(conversation.id) },
      { label: LEAFBOT.menuDelete, action: () => leafbotDeleteConversation(conversation.id) },
    ].forEach((item) => {
      const actionButton = document.createElement('button');
      actionButton.type = 'button';
      actionButton.className = 'leafbot-chatlist-menuitem';
      actionButton.textContent = item.label;
      actionButton.addEventListener('click', (event) => {
        event.stopPropagation();
        leafbotOpenMenuId = null;
        item.action();
      });
      menu.appendChild(actionButton);
    });

    menuWrap.appendChild(menuButton);
    menuWrap.appendChild(menu);
    row.appendChild(button);
    row.appendChild(menuWrap);
    leafbotChatListEl.appendChild(row);
  });
}

function leafbotRenderThreadFromHistory() {
  if (leafbotThreadEl) {
    leafbotThreadEl.innerHTML = '';
  }
  if (leafbotEmptyEl) {
    leafbotThreadEl.appendChild(leafbotEmptyEl);
    leafbotEmptyEl.hidden = leafbotHistory.length > 0;
  }
  leafbotSetChatActive(leafbotHistory.length > 0);

  leafbotHistory.forEach((message) => {
    leafbotRenderMessage(message.role, message.content);
  });

  if (leafbotFollowBottom && leafbotThreadEl) {
    leafbotThreadEl.scrollTop = leafbotThreadEl.scrollHeight;
  }
}

function leafbotSyncActiveConversation() {
  leafbotEnsureActiveConversation();
  const index = leafbotFindConversationIndex(leafbotActiveConversationId);
  if (index === -1) return;
  leafbotConversations[index].messages = leafbotHistory.slice();
  if (!leafbotConversations[index].pinned) {
    leafbotConversations[index].title = leafbotConversationTitle(leafbotHistory);
  }
  leafbotConversations[index].updatedAt = new Date().toISOString();
  leafbotSafeStorageWrite();
  leafbotRenderConversationList();
}

function leafbotLoadConversation(id) {
  const conversation = leafbotGetConversation(id);
  if (!conversation) return;
  leafbotActiveConversationId = id;
  leafbotOpenMenuId = null;
  leafbotHistory = Array.isArray(conversation.messages) ? conversation.messages.slice() : [];
  leafbotRenderThreadFromHistory();
  leafbotRenderConversationList();
  leafbotSetStatus(LEAFBOT.ready, false);
}

function leafbotDeleteConversation(id) {
  const index = leafbotFindConversationIndex(id);
  if (index === -1) return;

  const wasActive = leafbotActiveConversationId === id;
  leafbotConversations.splice(index, 1);

  if (leafbotConversations.length === 0) {
    const fresh = leafbotCreateConversation();
    leafbotConversations = [fresh];
  }

  if (wasActive) {
    leafbotActiveConversationId = leafbotOrderedConversations()[0].id;
    leafbotHistory = Array.isArray(leafbotGetConversation(leafbotActiveConversationId)?.messages)
      ? leafbotGetConversation(leafbotActiveConversationId).messages.slice()
      : [];
    leafbotRenderThreadFromHistory();
    leafbotPromptEl.value = '';
    leafbotUpdateSendState();
    leafbotSetStatus(LEAFBOT.ready, false);
  }

  leafbotOpenMenuId = null;
  leafbotSafeStorageWrite();
  leafbotRenderConversationList();
}

function leafbotRenameConversation(id) {
  const conversation = leafbotGetConversation(id);
  if (!conversation) return;
  const nextTitle = window.prompt(LEAFBOT.renamePrompt, conversation.title || LEAFBOT.historyUntitled);
  if (nextTitle === null) return;
  const trimmed = nextTitle.trim();
  if (!trimmed) return;
  conversation.title = trimmed.slice(0, 60);
  conversation.updatedAt = new Date().toISOString();
  leafbotSafeStorageWrite();
  leafbotRenderConversationList();
}

function leafbotTogglePinnedConversation(id) {
  const conversation = leafbotGetConversation(id);
  if (!conversation) return;
  conversation.pinned = !conversation.pinned;
  conversation.updatedAt = new Date().toISOString();
  leafbotSafeStorageWrite();
  leafbotRenderConversationList();
}

function leafbotToggleArchiveConversation(id) {
  const conversation = leafbotGetConversation(id);
  if (!conversation) return;
  conversation.archived = !conversation.archived;
  conversation.updatedAt = new Date().toISOString();
  leafbotSafeStorageWrite();
  leafbotRenderConversationList();
}

async function leafbotShareConversation(id) {
  const conversation = leafbotGetConversation(id);
  if (!conversation) return;
  const shareLink = leafbotBuildShareLink(conversation);
  const shareText = `${LEAFBOT.shareOpenPrompt} ${shareLink}`;
  const shareTitle = conversation.title || LEAFBOT.historyUntitled;

  const legacyCopy = () => {
    try {
      const helper = document.createElement('textarea');
      helper.value = shareText;
      helper.setAttribute('readonly', '');
      helper.style.position = 'fixed';
      helper.style.opacity = '0';
      helper.style.pointerEvents = 'none';
      document.body.appendChild(helper);
      helper.focus();
      helper.select();
      const copied = document.execCommand('copy');
      document.body.removeChild(helper);
      return copied;
    } catch (error) {
      return false;
    }
  };

  try {
    if (navigator.share) {
      await navigator.share({
        title: shareTitle,
        text: shareText,
        url: shareLink,
      });
      leafbotSetStatus(LEAFBOT.shareSuccess, false);
      return;
    }

    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(shareLink);
      leafbotSetStatus(LEAFBOT.shareCopied, false);
      window.alert(LEAFBOT.shareCopied);
      return;
    } else {
      const copied = legacyCopy();
      leafbotSetStatus(copied ? LEAFBOT.shareCopied : LEAFBOT.shareUnavailable, !copied);
      if (copied) {
        window.alert(LEAFBOT.shareCopied);
        return;
      }
    }
  } catch (error) {
    const copied = legacyCopy();
    if (copied) {
      leafbotSetStatus(LEAFBOT.shareCopied, false);
      window.alert(LEAFBOT.shareCopied);
      return;
    }
  }

  window.prompt(LEAFBOT.shareCopyPrompt, shareLink);
  leafbotSetStatus(LEAFBOT.shareFailed, true);
}

function leafbotStartNewChat() {
  leafbotHistory = [];
  const fresh = leafbotCreateConversation();
  leafbotConversations.unshift(fresh);
  leafbotActiveConversationId = fresh.id;
  leafbotOpenMenuId = null;
  leafbotSafeStorageWrite();
  leafbotRenderThreadFromHistory();
  leafbotRenderConversationList();
  leafbotPromptEl.value = '';
  leafbotUpdateSendState();
  leafbotSetStatus(LEAFBOT.ready, false);
}

function leafbotInitConversations() {
  leafbotConversations = leafbotSafeStorageRead().filter((conversation) => conversation && conversation.id);
  if (leafbotConversations.length === 0) {
    const fresh = leafbotCreateConversation();
    leafbotConversations = [fresh];
  }
  leafbotActiveConversationId = leafbotOrderedConversations()[0].id;
  leafbotHistory = Array.isArray(leafbotGetConversation(leafbotActiveConversationId)?.messages)
    ? leafbotGetConversation(leafbotActiveConversationId).messages.slice()
    : [];
  leafbotRenderThreadFromHistory();
  leafbotRenderConversationList();
  leafbotSafeStorageWrite();
}

function leafbotSetStatus(message, isError = false) {
  leafbotStatusEl.textContent = message;
  leafbotStatusEl.classList.toggle('is-error', isError);
}

function leafbotSetChatActive(isActive) {
  if (!leafbotShellEl) return;
  leafbotShellEl.classList.toggle('leafbot-chat-active', isActive);
}

function leafbotUpdateMicState() {
  if (!leafbotMicEl) return;
  leafbotMicEl.classList.toggle('is-listening', leafbotListening);
  leafbotMicEl.setAttribute('aria-label', leafbotListening ? LEAFBOT.voiceStop : LEAFBOT.voice);
  leafbotMicEl.title = leafbotListening ? LEAFBOT.voiceStop : LEAFBOT.voice;
}

function leafbotSetBusy(isBusy) {
  leafbotSendEl.disabled = isBusy;
  leafbotPromptEl.disabled = isBusy;
  if (leafbotMicEl) leafbotMicEl.disabled = isBusy;
  leafbotSendEl.classList.toggle('is-busy', isBusy);
  leafbotUpdateSendState();
}

function leafbotUpdateSendState() {
  const hasText = leafbotPromptEl.value.trim().length > 0;
  leafbotSendEl.classList.toggle('is-ready', hasText && !leafbotPromptEl.disabled);
}

function leafbotRenderMessage(role, text) {
  if (leafbotEmptyEl) {
    leafbotEmptyEl.hidden = true;
  }

  leafbotSetChatActive(true);
  const shouldStickToBottom = role === 'user' || leafbotFollowBottom;

  const item = document.createElement('div');
  item.className = `leafbot-message leafbot-message-${role}`;

  const label = document.createElement('div');
  label.className = 'leafbot-message-label';
  label.textContent = role === 'assistant' ? 'LeafBot' : LEAFBOT.labelYou;

  const bubble = document.createElement('div');
  bubble.className = 'leafbot-message-bubble';
  bubble.textContent = text;

  item.appendChild(label);
  item.appendChild(bubble);
  leafbotThreadEl.appendChild(item);
  if (shouldStickToBottom) {
    leafbotThreadEl.scrollTop = leafbotThreadEl.scrollHeight;
  }
}

async function leafbotSend(promptText) {
  const prompt = (promptText || leafbotPromptEl.value).trim();
  if (!prompt) {
    leafbotSetStatus(LEAFBOT.promptError, true);
    return;
  }

  leafbotRenderMessage('user', prompt);
  leafbotFollowBottom = true;
  leafbotHistory.push({ role: 'user', content: prompt });
  leafbotSyncActiveConversation();
  leafbotPromptEl.value = '';
  leafbotSetStatus(LEAFBOT.thinking, false);
  leafbotSetBusy(true);

  try {
    const res = await fetch('api_leafbot.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        csrf_token: LEAFBOT.csrf,
        prompt,
        history: leafbotHistory.slice(-8),
      }),
    });

    const data = await res.json();
    if (!res.ok || !data.reply) {
      const errorMessage = data.error || LEAFBOT.networkError;
      leafbotSetStatus(errorMessage, true);
      return;
    }

    leafbotHistory.push({ role: 'assistant', content: data.reply });
    leafbotRenderMessage('assistant', data.reply);
    leafbotSyncActiveConversation();
    leafbotSetStatus(LEAFBOT.ready, false);
  } catch (error) {
    leafbotSetStatus(LEAFBOT.networkError, true);
  } finally {
    leafbotSetBusy(false);
    if (window.matchMedia('(pointer:fine)').matches) {
      leafbotPromptEl.focus();
    }
  }
}

function leafbotStopListening(resetStatus = true) {
  leafbotListening = false;
  if (leafbotRecognition) {
    leafbotRecognition.onend = null;
    leafbotRecognition.stop();
    leafbotRecognition.onend = leafbotHandleRecognitionEnd;
  }
  leafbotUpdateMicState();
  if (resetStatus) {
    leafbotSetStatus(LEAFBOT.ready, false);
  }
}

function leafbotHandleRecognitionEnd() {
  leafbotListening = false;
  leafbotUpdateMicState();
  leafbotSetStatus(LEAFBOT.ready, false);
  leafbotUpdateSendState();
}

function leafbotInitVoice() {
  if (!leafbotMicEl) return;

  if (!SpeechRecognition) {
    leafbotMicEl.disabled = true;
    leafbotMicEl.classList.add('is-disabled');
    leafbotSetStatus(LEAFBOT.micUnsupported, true);
    return;
  }

  leafbotRecognition = new SpeechRecognition();
  leafbotRecognition.lang = LEAFBOT.recognitionLang;
  leafbotRecognition.interimResults = true;
  leafbotRecognition.continuous = true;

  leafbotRecognition.onresult = (event) => {
    let transcript = '';
    for (let i = event.resultIndex; i < event.results.length; i += 1) {
      transcript += event.results[i][0].transcript;
    }
    leafbotPromptEl.value = `${leafbotTranscriptBase}${transcript}`.trim();
    leafbotUpdateSendState();
  };

  leafbotRecognition.onerror = (event) => {
    leafbotListening = false;
    leafbotUpdateMicState();
    if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
      leafbotSetStatus(LEAFBOT.micDenied, true);
      return;
    }
    leafbotSetStatus(LEAFBOT.micError, true);
  };

  leafbotRecognition.onend = leafbotHandleRecognitionEnd;

  leafbotMicEl.addEventListener('click', () => {
    if (leafbotListening) {
      leafbotStopListening();
      return;
    }

    leafbotTranscriptBase = leafbotPromptEl.value.trim();
    if (leafbotTranscriptBase) {
      leafbotTranscriptBase += ' ';
    }

    try {
      leafbotRecognition.start();
      leafbotListening = true;
      leafbotUpdateMicState();
      leafbotSetStatus(LEAFBOT.listening, false);
    } catch (error) {
      leafbotSetStatus(LEAFBOT.micError, true);
    }
  });
}

document.addEventListener('click', (event) => {
  if (!event.target.closest('.leafbot-chatlist-menuwrap') && leafbotOpenMenuId !== null) {
    leafbotOpenMenuId = null;
    leafbotRenderConversationList();
  }
});

leafbotSendEl.addEventListener('click', () => leafbotSend());
leafbotNewChatEl.addEventListener('click', leafbotStartNewChat);
leafbotSidebarToggleEl.addEventListener('click', () => {
  leafbotSetSidebarCollapsed(!leafbotShellEl.classList.contains('leafbot-sidebar-collapsed'));
});
leafbotPromptEl.addEventListener('input', leafbotUpdateSendState);
leafbotPromptEl.addEventListener('keydown', (event) => {
  if (event.key !== 'Enter' || event.shiftKey) return;
  event.preventDefault();
  leafbotSend();
});
leafbotThreadEl.addEventListener('scroll', () => {
  const distance = leafbotThreadEl.scrollHeight - leafbotThreadEl.scrollTop - leafbotThreadEl.clientHeight;
  leafbotFollowBottom = distance < 72;
});

leafbotInitConversations();
leafbotImportSharedConversation();
leafbotSetSidebarCollapsed(leafbotSidebarCollapsed());
leafbotUpdateSendState();
leafbotUpdateMicState();
leafbotInitVoice();
</script>

<?php require __DIR__ . '/../app/views/footer.php'; ?>
