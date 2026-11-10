// Main JavaScript - Secrétariat RMC BtpSarl

let selectedCompanyId = 0;
let cachedClients = [];
let currentPage = 1;
let perPage = 20;
let useDateRange = false;

document.addEventListener('DOMContentLoaded', () => {
    // Charger le thème sauvegardé
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeButton(savedTheme);
    
    loadClients();
    loadTodayPayments();

    const form = document.getElementById('paymentForm');
    if (form) {
        form.addEventListener('submit', handlePaymentSubmit);
    }

    // Sélectionner la première carte par défaut
    const firstCard = document.querySelector('.company-select-card');
    if (firstCard) {
        firstCard.click();
    }
});

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

async function loadClients() {
    const selectElem = document.getElementById('client_select');
    if (!selectElem) return;

    try {
        const response = await fetch('api.php?action=get_clients');
        const result = await response.json();

        if (result.status === 'success' && result.data) {
            cachedClients = result.data;
            selectElem.innerHTML = `<option value="">-- Sélectionner un client existant ou saisir ci-dessous --</option>` +
                cachedClients.map(c => `<option value="${c.id}" data-name="${escapeHtml(c.name)}" data-phone="${escapeHtml(c.phone || '')}">${escapeHtml(c.name)} ${c.phone ? '(' + escapeHtml(c.phone) + ')' : ''}</option>`).join('');
        }
    } catch (err) {
        console.error('Erreur chargement clients:', err);
    }
}

function onClientSelectChange(selectElem) {
    const selectedOption = selectElem.options[selectElem.selectedIndex];
    if (!selectedOption || !selectedOption.value) return;

    const name = selectedOption.getAttribute('data-name');
    const phone = selectedOption.getAttribute('data-phone');

    document.getElementById('client_name').value = name || '';
    document.getElementById('client_phone').value = phone || '';
}

function resetClientSelection() {
    const selectElem = document.getElementById('client_select');
    if (selectElem) selectElem.value = '';
    document.getElementById('client_name').value = '';
    document.getElementById('client_phone').value = '';
    document.getElementById('client_name').focus();
}

function selectCompanyCard(id, element) {
    document.querySelectorAll('.company-select-card').forEach(card => card.classList.remove('selected'));
    element.classList.add('selected');

    selectedCompanyId = id;
    document.getElementById('company_id').value = id;

    const compName = element.querySelector('.company-card-title').textContent;
    const badge = document.getElementById('selectedCompanyBadge');
    if (badge) {
        badge.style.display = 'inline-flex';
        badge.textContent = 'Rubrique : ' + compName;
    }

    // Filtrer dynamiquement la liste de la journée en temps réel pour cette rubrique
    loadTodayPayments(id);
}

function resetCompanyFilter() {
    document.querySelectorAll('.company-select-card').forEach(card => card.classList.remove('selected'));
    selectedCompanyId = 0;
    document.getElementById('company_id').value = '';

    const badge = document.getElementById('selectedCompanyBadge');
    if (badge) {
        badge.style.display = 'none';
    }

    loadTodayPayments(0);
}

function calculateBalance() {
    const total = parseFloat(document.getElementById('total_amount').value) || 0;
    const advance = parseFloat(document.getElementById('advance_paid').value) || 0;
    const remaining = Math.max(0, total - advance);
    
    const remainingDisplay = document.getElementById('remaining_display');
    if (remainingDisplay) {
        remainingDisplay.textContent = formatMoney(remaining) + ' FCFA';
        if (remaining === 0 && total > 0) {
            remainingDisplay.style.color = 'var(--success)';
            remainingDisplay.style.background = 'var(--success-soft)';
            remainingDisplay.style.borderColor = 'var(--success)';
        } else {
            remainingDisplay.style.color = '#1D4ED8';
            remainingDisplay.style.background = '#EFF6FF';
            remainingDisplay.style.borderColor = '#2563EB';
        }
    }
}

function formatMoney(amount) {
    return new Intl.NumberFormat('fr-FR').format(amount);
}

async function handlePaymentSubmit(e) {
    e.preventDefault();

    const companyId = document.getElementById('company_id').value;
    if (!companyId) {
        showAlert('danger', '⚠️ Veuillez cliquer sur une carte d\'entreprise ci-dessus pour la sélectionner.');
        return;
    }

    const btnSubmit = document.getElementById('btnSubmit');
    btnSubmit.disabled = true;
    btnSubmit.textContent = '⌛ Enregistrement en cours...';

    const formData = new FormData(e.target);
    formData.append('action', 'add_payment');

    try {
        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.status === 'success') {
            showAlert('success', '✅ ' + result.message);
            e.target.reset();
            calculateBalance();
            loadClients(); // Recharger la liste des clients maj
            loadTodayPayments(selectedCompanyId);
            
            // Ouvrir le reçu pour impression
            openReceiptPrint(result.payment_id);
        } else {
            showAlert('danger', '❌ ' + result.message);
        }
    } catch (err) {
        showAlert('danger', '❌ Erreur de réseau ou du serveur.');
        console.error(err);
    } finally {
        btnSubmit.disabled = false;
        btnSubmit.textContent = '💾 Enregistrer & Imprimer le Reçu';
    }
}

async function loadTodayPayments(companyIdOverride = null, page = 1) {
    const tableBody = document.getElementById('paymentsTableBody');
    if (!tableBody) return;

    const companyIdToUse = companyIdOverride !== null ? companyIdOverride : selectedCompanyId;
    const dateVal = document.getElementById('filterDate') ? document.getElementById('filterDate').value : '';
    const searchVal = document.getElementById('filterSearch') ? document.getElementById('filterSearch').value : '';
    const dateFrom = document.getElementById('dateFrom')?.value || '';
    const dateTo = document.getElementById('dateTo')?.value || '';

    currentPage = page;

    try {
        let url = `api.php?action=get_payments&company_id=${companyIdToUse}&search=${encodeURIComponent(searchVal)}&page=${page}&per_page=${perPage}`;
        if (useDateRange && dateFrom && dateTo) {
            url += `&date_from=${dateFrom}&date_to=${dateTo}`;
        } else {
            url += `&date=${dateVal}`;
        }
        
        const response = await fetch(url);
        const result = await response.json();

        if (result.status === 'success') {
            renderPaymentsTable(result.data);
            renderPagination(result.pagination);
        }
    } catch (err) {
        console.error('Erreur chargement paiements:', err);
    }
}

function renderPaymentsTable(payments) {
    const tableBody = document.getElementById('paymentsTableBody');
    if (!tableBody) return;

    if (!payments || payments.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">Aucun encaissement trouvé pour cette sélection.</td></tr>`;
        return;
    }

    tableBody.innerHTML = payments.map(p => {
        const remaining = parseFloat(p.remaining_amount);
        const statusBadge = remaining <= 0 
            ? `<span class="badge badge-solde">SOLDE OK</span>` 
            : `<span class="badge badge-avance">AVANCE (Reste: ${formatMoney(remaining)} FCFA)</span>`;

        return `
            <tr>
                <td><strong>${escapeHtml(p.receipt_number)}</strong></td>
                <td><span class="badge badge-company">${escapeHtml(p.company_code)}</span></td>
                <td>
                    <div><strong>${escapeHtml(p.client_name)}</strong></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">${escapeHtml(p.client_phone || 'Sans tél')}</div>
                </td>
                <td>
                    <div><strong>${formatMoney(p.advance_paid)} FCFA</strong></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Total: ${formatMoney(p.total_amount)} FCFA</div>
                </td>
                <td>${statusBadge}</td>
                <td>
                    <button class="btn btn-secondary btn-sm" onclick="openReceiptPrint(${p.id})">
                        🖨️ Reçu
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function renderPagination(pagination) {
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
                <button class="btn btn-secondary btn-sm" onclick="loadTodayPayments(null, ${pagination.page - 1})" ${pagination.page === 1 ? 'disabled' : ''}>
                    ← Précédent
                </button>
                <span style="display: flex; align-items: center; font-weight: 600; padding: 0 8px;">
                    Page ${pagination.page} / ${pagination.total_pages}
                </span>
                <button class="btn btn-secondary btn-sm" onclick="loadTodayPayments(null, ${pagination.page + 1})" ${pagination.page === pagination.total_pages ? 'disabled' : ''}>
                    Suivant →
                </button>
            </div>
        </div>
    `;

    tableContainer.insertAdjacentHTML('afterend', paginationHtml);
}

function openReceiptPrint(paymentId) {
    const printWindow = window.open(`print_receipt.php?id=${paymentId}`, '_blank', 'width=850,height=900');
    if (printWindow) {
        printWindow.focus();
    }
}

function printFilteredHistory() {
    const dateVal = document.getElementById('filterDate') ? document.getElementById('filterDate').value : '';
    const searchVal = document.getElementById('filterSearch') ? document.getElementById('filterSearch').value : '';
    const dateFrom = document.getElementById('dateFrom')?.value || '';
    const dateTo = document.getElementById('dateTo')?.value || '';
    
    let url = `print_history.php?company_id=${selectedCompanyId}&search=${encodeURIComponent(searchVal)}`;
    if (useDateRange && dateFrom && dateTo) {
        url += `&date_from=${dateFrom}&date_to=${dateTo}`;
    } else {
        url += `&date=${dateVal}`;
    }
    window.open(url, '_blank', 'width=950,height=900');
}

function applyDateRange() {
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    
    if (!dateFrom || !dateTo) {
        return;
    }
    
    useDateRange = true;
    loadTodayPayments();
}

function resetDateRange() {
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    useDateRange = false;
    loadTodayPayments();
}

function exportToCSV() {
    const dateVal = document.getElementById('filterDate') ? document.getElementById('filterDate').value : '';
    const searchVal = document.getElementById('filterSearch') ? document.getElementById('filterSearch').value : '';
    const dateFrom = document.getElementById('dateFrom')?.value || '';
    const dateTo = document.getElementById('dateTo')?.value || '';
    
    let url = `export_csv.php?company_id=${selectedCompanyId}&search=${encodeURIComponent(searchVal)}`;
    if (useDateRange && dateFrom && dateTo) {
        url += `&date_from=${dateFrom}&date_to=${dateTo}`;
    } else {
        url += `&date=${dateVal}`;
    }
    window.open(url, '_blank');
}

// Recherche de client
async function searchClient() {
    const search = document.getElementById('clientSearch').value.trim();
    if (!search) {
        showAlert('danger', 'Veuillez entrer un terme de recherche.');
        return;
    }

    try {
        const response = await fetch(`api.php?action=search_client_payments&search=${encodeURIComponent(search)}`);
        const result = await response.json();

        if (result.status === 'success') {
            displayClientSearchResults(result.data);
            document.getElementById('clientSearchModal').classList.add('active');
        } else {
            showAlert('danger', result.message);
        }
    } catch (err) {
        showAlert('danger', 'Erreur de recherche.');
    }
}

function displayClientSearchResults(payments) {
    const container = document.getElementById('clientSearchResults');
    
    if (payments.length === 0) {
        container.innerHTML = '<p style="text-align: center; padding: 20px; color: #64748B;">Aucun résultat trouvé.</p>';
        return;
    }

    let html = '<table class="table"><thead><tr><th>Reçu N°</th><th>Client</th><th>Total</th><th>Payé</th><th>Reste</th><th>Actions</th></tr></thead><tbody>';
    
    payments.forEach(p => {
        const totalPaid = parseFloat(p.advance_paid) + (parseFloat(p.additional_payments_sum) || 0);
        const remaining = parseFloat(p.total_amount) - totalPaid;
        
        html += `
            <tr>
                <td>${escapeHtml(p.receipt_number)}</td>
                <td>${escapeHtml(p.client_name)}</td>
                <td>${formatMoney(p.total_amount)} FCFA</td>
                <td style="color: #10B981;">${formatMoney(totalPaid)} FCFA</td>
                <td style="color: ${remaining <= 0 ? '#10B981' : '#EF4444'};">${formatMoney(remaining)} FCFA</td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick="openAdditionalPaymentModal(${p.id})">
                        💰 Ajouter Versement
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="openReceiptPrint(${p.id})">
                        🖨️ Voir Reçu
                    </button>
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table>';
    container.innerHTML = html;
}

function closeClientSearchModal() {
    document.getElementById('clientSearchModal').classList.remove('active');
}

// Modal versement supplémentaire
function openAdditionalPaymentModal(parentId) {
    closeClientSearchModal();
    
    // Récupérer les détails du paiement parent
    fetch(`api.php?action=get_payments&payment_id=${parentId}`)
        .then(r => r.json())
        .then(result => {
            if (result.status === 'success' && result.data.length > 0) {
                const payment = result.data[0];
                const totalPaid = parseFloat(payment.advance_paid) + (parseFloat(payment.additional_payments_sum) || 0);
                const remaining = parseFloat(payment.total_amount) - totalPaid;
                
                document.getElementById('parent_payment_id').value = parentId;
                document.getElementById('parentPaymentInfo').innerHTML = `
                    <strong>Client :</strong> ${escapeHtml(payment.client_name)}<br>
                    <strong>Reçu N° :</strong> ${escapeHtml(payment.receipt_number)}<br>
                    <strong>Total Facturé :</strong> ${formatMoney(payment.total_amount)} FCFA<br>
                    <strong>Déjà Payé :</strong> ${formatMoney(totalPaid)} FCFA<br>
                    <strong>Reste à Payer :</strong> <span style="color: ${remaining <= 0 ? '#10B981' : '#EF4444'}; font-weight: 700;">${formatMoney(remaining)} FCFA</span>
                `;
                
                document.getElementById('additionalPaymentModal').classList.add('active');
            }
        });
}

function closeAdditionalPaymentModal() {
    document.getElementById('additionalPaymentModal').classList.remove('active');
    document.getElementById('additionalPaymentForm').reset();
}

// Formulaire versement supplémentaire
document.getElementById('additionalPaymentForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btnAdditionalSubmit');
    btn.disabled = true;
    btn.textContent = '⌛ Enregistrement...';

    const formData = new FormData(e.target);
    formData.append('action', 'add_payment');
    formData.append('company_id', selectedCompanyId);

    try {
        const response = await fetch('api.php', { method: 'POST', body: formData });
        const result = await response.json();

        if (result.status === 'success') {
            showAlert('success', '✅ ' + result.message);
            closeAdditionalPaymentModal();
            openReceiptPrint(result.payment_id);
            loadTodayPayments();
        } else {
            showAlert('danger', '❌ ' + result.message);
        }
    } catch (err) {
        showAlert('danger', '❌ Erreur de réseau.');
    } finally {
        btn.disabled = false;
        btn.textContent = '💾 Enregistrer le Versement';
    }
});

function showAlert(type, message) {
    const area = document.getElementById('notificationArea');
    if (!area) return;
    
    // Créer l'élément toast
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <span class="toast-icon">${type === 'success' ? '✅' : type === 'danger' ? '❌' : 'ℹ️'}</span>
            <span class="toast-message">${message}</span>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">×</button>
    `;
    
    // Ajouter au DOM
    area.appendChild(toast);
    
    // Animationd'entrée
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Auto-suppression après 5 secondes
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
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
