// Boss Dashboard JavaScript - RMC BtpSarl

document.addEventListener('DOMContentLoaded', () => {
    // Charger le thème sauvegardé
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeButton(savedTheme);
    
    loadBossDashboard();
});

let currentStatsData = null;
let currentPage = 1;
let perPage = 25;
let useDateRange = false;

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    const newTheme = currentTheme === 'light' ? 'dark' : 'light';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateThemeButton(newTheme);
}

function updateThemeButton(theme) {
    const btn = document.getElementById('themeToggle');
    if (btn) {
        btn.textContent = theme === 'light' ? '🌙 Mode Sombre' : '☀️ Mode Clair';
    }
}

async function loadBossDashboard(page = 1) {
    const dateVal = document.getElementById('bossDate').value;
    const companyVal = document.getElementById('bossCompany').value;
    const searchVal = document.getElementById('bossSearch') ? document.getElementById('bossSearch').value : '';
    const dateFrom = document.getElementById('dateFrom')?.value || '';
    const dateTo = document.getElementById('dateTo')?.value || '';

    currentPage = page;

    try {
        // 1. Charger les statistiques globales
        let statsUrl = `api.php?action=get_stats&date=${dateVal}&company_id=${companyVal}`;
        if (useDateRange && dateFrom && dateTo) {
            statsUrl += `&date_from=${dateFrom}&date_to=${dateTo}`;
        }
        const statsRes = await fetch(statsUrl);
        const stats = await statsRes.json();

        if (stats.status === 'success') {
            currentStatsData = stats;
            renderStats(stats);
        }

        // 2. Charger le tableau des encaissements avec pagination
        let paymentsUrl = `api.php?action=get_payments&company_id=${companyVal}&search=${encodeURIComponent(searchVal)}&page=${page}&per_page=${perPage}`;
        if (useDateRange && dateFrom && dateTo) {
            paymentsUrl += `&date_from=${dateFrom}&date_to=${dateTo}`;
        } else {
            paymentsUrl += `&date=${dateVal}`;
        }
        const paymentsRes = await fetch(paymentsUrl);
        const payments = await paymentsRes.json();

        if (payments.status === 'success') {
            renderBossTable(payments.data);
            renderBossPagination(payments.pagination);
        }
    } catch (err) {
        console.error('Erreur chargement Dashboard Boss:', err);
    }
}

function renderStats(stats) {
    document.getElementById('statCollected').textContent = formatMoney(stats.totals.collected) + ' FCFA';
    document.getElementById('statBusiness').textContent = formatMoney(stats.totals.business) + ' FCFA';
    document.getElementById('statRemaining').textContent = formatMoney(stats.totals.remaining) + ' FCFA';
    document.getElementById('statCount').textContent = stats.totals.count + ' transaction(s) enregistrée(s)';

    // Grille des filiales
    const grid = document.getElementById('companyBreakdownGrid');
    if (grid && stats.by_company) {
        grid.innerHTML = stats.by_company.map(c => `
            <div style="background: #F8FAFC; border-radius: var(--radius-md); padding: 16px; border: 1px solid var(--border-color);">
                <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                    ${escapeHtml(c.name)} (${escapeHtml(c.code)})
                </div>
                <div style="font-size: 1.25rem; font-weight: 800; color: var(--primary); margin-top: 6px;">
                    ${formatMoney(c.total_collected)} FCFA
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                    ${c.total_count} encaissement(s)
                </div>
            </div>
        `).join('');
    }
}

function renderBossTable(payments) {
    const tbody = document.getElementById('bossTableBody');
    if (!tbody) return;

    if (!payments || payments.length === 0) {
        tbody.innerHTML = `<tr><td colspan="10" style="text-align: center; color: var(--text-muted); padding: 24px;">Aucun enregistrement pour cette date/sélection.</td></tr>`;
        return;
    }

    tbody.innerHTML = payments.map(p => {
        const remaining = parseFloat(p.remaining_amount);
        const statusBadge = remaining <= 0 
            ? `<span class="badge badge-solde">SOLDE</span>` 
            : `<span class="badge badge-avance">AVANCE</span>`;

        const createdTime = new Date(p.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

        return `
            <tr>
                <td>
                    <div><strong>${escapeHtml(p.receipt_number)}</strong></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">${createdTime}</div>
                </td>
                <td><span class="badge badge-company">${escapeHtml(p.company_code)}</span></td>
                <td>
                    <div><strong>${escapeHtml(p.client_name)}</strong></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">${escapeHtml(p.client_phone || 'Sans contact')}</div>
                </td>
                <td style="max-width: 220px; font-size: 0.85rem;">
                    ${escapeHtml(p.description)}
                </td>
                <td><span class="badge" style="background: #EEF2FF; color: #4F46E5;">${escapeHtml(p.payment_method)}</span></td>
                <td>${formatMoney(p.total_amount)} FCFA</td>
                <td style="font-weight: 800; color: var(--success);">${formatMoney(p.advance_paid)} FCFA</td>
                <td style="font-weight: 700; color: ${remaining > 0 ? 'var(--danger)' : 'var(--text-muted)'};">
                    ${formatMoney(remaining)} FCFA
                </td>
                <td style="font-size: 0.8rem; color: var(--text-muted);">${escapeHtml(p.secretary_name)}</td>
                <td>
                    <div style="display: flex; gap: 6px;">
                        <button class="btn btn-secondary btn-sm" onclick="openReceiptPrint(${p.id})">🖨️</button>
                        <button class="btn btn-sm" style="background: var(--danger-soft); color: var(--danger);" onclick="deletePayment(${p.id})">🗑️</button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function renderBossPagination(pagination) {
    const tableContainer = document.querySelector('.table-responsive');
    if (!tableContainer || !pagination) return;

    // Supprimer l'ancienne pagination si elle existe
    const oldPagination = tableContainer.querySelector('.pagination-container');
    if (oldPagination) oldPagination.remove();

    if (pagination.total_pages <= 1) return;

    const paginationHtml = `
        <div class="pagination-container" style="margin-top: 16px; display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8fafc; border-radius: 8px;">
            <div style="font-size: 0.875rem; color: #64748b;">
                Affichage ${((pagination.page - 1) * pagination.per_page) + 1}-${Math.min(pagination.page * pagination.per_page, pagination.total)} sur ${pagination.total} enregistrements
            </div>
            <div style="display: flex; gap: 8px;">
                <button class="btn btn-secondary btn-sm" onclick="loadBossDashboard(${pagination.page - 1})" ${pagination.page === 1 ? 'disabled' : ''}>
                    ← Précédent
                </button>
                <span style="display: flex; align-items: center; font-weight: 600; padding: 0 8px;">
                    Page ${pagination.page} / ${pagination.total_pages}
                </span>
                <button class="btn btn-secondary btn-sm" onclick="loadBossDashboard(${pagination.page + 1})" ${pagination.page === pagination.total_pages ? 'disabled' : ''}>
                    Suivant →
                </button>
            </div>
        </div>
    `;

    tableContainer.insertAdjacentHTML('afterend', paginationHtml);
}

async function deletePayment(id) {
    if (!confirm("Voulez-vous vraiment supprimer cet encaissement ? Cette action est irréversible.")) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_payment');
    formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
    formData.append('payment_id', id);

    try {
        const res = await fetch('api.php', { method: 'POST', body: formData });
        const json = await res.json();
        if (json.status === 'success') {
            alert("✅ " + json.message);
            loadBossDashboard();
        } else {
            alert("❌ " + json.message);
        }
    } catch (e) {
        alert("Erreur de suppression");
    }
}

function printDailyReport() {
    const dateVal = document.getElementById('bossDate').value;
    const companyVal = document.getElementById('bossCompany').value;
    window.open(`print_report.php?date=${dateVal}&company_id=${companyVal}`, '_blank', 'width=950,height=900');
}

function openWhatsAppModal() {
    if (!currentStatsData) return;

    const dateVal = document.getElementById('bossDate').value;
    const formattedDate = new Date(dateVal).toLocaleDateString('fr-FR');

    let text = `🏢 *BILAN JOURNALIER DE CAISSE - RMC BtpSarl*\n`;
    text += `📅 Date : *${formattedDate}*\n`;
    text += `📍 Yaoundé - Quartier Fougerole\n`;
    text += `-------------------------------------------\n\n`;

    text += `💰 *TOTAL ENCAISSÉ DU JOUR : ${formatMoney(currentStatsData.totals.collected)} FCFA*\n`;
    text += `📊 Chiffre d'Affaires Engagé : ${formatMoney(currentStatsData.totals.business)} FCFA\n`;
    text += `⏳ Restes à Recouvrer : ${formatMoney(currentStatsData.totals.remaining)} FCFA\n`;
    text += `🔢 Nombre de Transactions : ${currentStatsData.totals.count}\n\n`;

    text += `🏛️ *DÉTAIL PAR FILIALE :*\n`;
    if (currentStatsData.by_company) {
        currentStatsData.by_company.forEach(c => {
            text += `• *${c.name}* : ${formatMoney(c.total_collected)} FCFA (${c.total_count} encaissement(s))\n`;
        });
    }

    text += `\n💵 *MODE DE RÈGLEMENT :*\n`;
    if (currentStatsData.by_method) {
        currentStatsData.by_method.forEach(m => {
            text += `• ${m.payment_method} : ${formatMoney(m.total_collected)} FCFA\n`;
        });
    }

    text += `\n-------------------------------------------\n`;
    text += `Document généré automatiquement par le Système de Caisse RMC BtpSarl.`;

    document.getElementById('whatsappText').value = text;
    document.getElementById('whatsappModal').classList.add('active');
}

function closeWhatsAppModal() {
    document.getElementById('whatsappModal').classList.remove('active');
}

function copyWhatsAppText() {
    const textarea = document.getElementById('whatsappText');
    textarea.select();
    document.execCommand('copy');
    alert('✅ Récapitulatif copié dans le presse-papier ! Vous pouvez maintenant le coller dans WhatsApp.');
}

function openReceiptPrint(paymentId) {
    window.open(`print_receipt.php?id=${paymentId}`, '_blank', 'width=850,height=900');
}

function exportToCSV() {
    const dateVal = document.getElementById('bossDate').value;
    const companyVal = document.getElementById('bossCompany').value;
    const searchVal = document.getElementById('bossSearch') ? document.getElementById('bossSearch').value : '';
    const dateFrom = document.getElementById('dateFrom')?.value || '';
    const dateTo = document.getElementById('dateTo')?.value || '';
    
    let url = `export_csv.php?company_id=${companyVal}&search=${encodeURIComponent(searchVal)}`;
    if (useDateRange && dateFrom && dateTo) {
        url += `&date_from=${dateFrom}&date_to=${dateTo}`;
    } else {
        url += `&date=${dateVal}`;
    }
    
    window.open(url, '_blank');
}

function applyDateRange() {
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    
    if (!dateFrom || !dateTo) {
        alert('Veuillez sélectionner une date de début et de fin.');
        return;
    }
    
    useDateRange = true;
    loadBossDashboard();
}

function resetDateRange() {
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    useDateRange = false;
    loadBossDashboard();
}

function triggerBackup() {
    if (!confirm('Voulez-vous lancer une sauvegarde de la base de données ? Cela peut prendre quelques secondes.')) {
        return;
    }
    window.open('backup_db.php', '_blank', 'width=400,height=300');
}

function formatMoney(amount) {
    return new Intl.NumberFormat('fr-FR').format(amount);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
