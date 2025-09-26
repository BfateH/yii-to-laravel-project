document.addEventListener("DOMContentLoaded", function() {
    if (!window.MoonShineApp || typeof window.MoonShineApp.userRole === 'undefined') {
        console.warn('MoonShineApp data not available or incomplete. Exiting listener.');
        return;
    }

    const currentUserId = +window.MoonShineApp.currentUserId;
    const userRole = +window.MoonShineApp.userRole;
    const isAdminRole = userRole === 1;
    const isDefaultUserRole = userRole === 2;
    const isPartnerRole = userRole === 3;

    if (!window.Echo) {
        console.error('Laravel Echo is not initialized. Cannot set up listeners.');
        return;
    }

    // Каналы админов
    if(isAdminRole) {
        const echoChannel = window.Echo.private('admin.tickets');

        echoChannel.listen('.ticket.created', (res) => {
            if (res.ticket) {
                MoonShine.ui.toast(`Создан новый тикет #${res.ticket.id}`, "success", 0);
            }
        });

        echoChannel.listen('.message.sent', (res) => {
            if (res.message) {
                if (+res.message.user.id !== currentUserId) {
                    MoonShine.ui.toast(`Новое сообщение в Тикет #${res.message.ticket_id}`, "success", 0);
                }
            }
        });
    }

    // Каналы обычных пользователей
    if(isDefaultUserRole) {

    }

    // Каналы партнеров
    if(isPartnerRole) {
        const echoChannel = window.Echo.private(`partner.${currentUserId}.tickets`);

        echoChannel.listen('.ticket.created', (res) => {
            if (res.ticket) {
                MoonShine.ui.toast(`Создан новый тикет #${res.ticket.id}`, "success", 0);
            }
        });

        echoChannel.listen('.message.sent', (res) => {
            if (res.message) {
                if (+res.message.user.id !== currentUserId) {
                    MoonShine.ui.toast(`Новое сообщение в Тикет #${res.message.ticket_id}`, "success", 0);
                }
            }
        });
    }
});
