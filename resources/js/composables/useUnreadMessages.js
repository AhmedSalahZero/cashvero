import { ref } from 'vue';

/**
 * The live unread-messages count behind the sidebar "Messages" badge.
 *
 * Normally that badge comes from the server on every Inertia response
 * (SidebarMenu::build() -> sidebarMenu.messages.badge), which means it's
 * only ever as fresh as your last page load: sit on any screen for an
 * hour and it still shows what was true when you arrived. The Messages
 * inbox is already polling for new mail, so it writes the real count in
 * here and AppLayout prefers it over the stale prop.
 *
 * Module scope on purpose — AppLayout is rebuilt on every Inertia visit
 * (same reasoning as useToasts), so this has to outlive any one
 * component instance.
 *
 * The inbox clears it on unmount: away from that page nothing is
 * polling, and the server-rendered prop is then the fresher of the two.
 */
const unreadMessages = ref(null);

export function useUnreadMessages() {
    function setUnreadMessages(count) {
        unreadMessages.value = Number.isFinite(count) ? count : null;
    }

    function clearUnreadMessages() {
        unreadMessages.value = null;
    }

    return { unreadMessages, setUnreadMessages, clearUnreadMessages };
}
