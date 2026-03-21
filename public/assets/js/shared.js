// shared.js - Shared utility functions for all views

// Escape HTML special characters in a string
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}


// Localize order status label
function localizeOrderStatusLabel(status) {
    switch (status) {
        case 'Pending':
        case 'pending': return 'Väntar';
        case 'In Progress':
        case 'in_progress': return 'Pågår';
        case 'Done':
        case 'done': return 'Klar';
        case 'Delivered':
        case 'delivered': return 'Levererad';
        default: return status;
    }
}

// Localize order item status label
function localizeItemStatusLabel(status) {
    switch (status) {
        case 'Pending':
        case 'pending': return 'Väntar';
        case 'In Progress':
        case 'in_progress': return 'Pågår';
        case 'Done':
        case 'done': return 'Klar';
        case 'Delivered':
        case 'delivered': return 'Levererad';
        default: return status;
    }
}

// Export for module use if needed
if (typeof module !== 'undefined') {
    module.exports = { escapeHtml, localizeOrderStatusLabel, localizeItemStatusLabel };
}
