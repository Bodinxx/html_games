/**
 * dashboard.js – Dashboard logic for individual entry tracking
 * Depends on window.App (app.js)
 */
(function () {
    'use strict';

    let activitiesCatalog = {};   // { name: {unit, factor, category} }
    let weekEntries = [];         // All entries for current week
    let userGoals = {};
    let userProfile = {};

    /* ── Constants ── */
    const ENTRY_TYPES = {
        'sleep': { icon: '😴', label: 'Sleep', unit: 'hours' },
        'meal': { icon: '🥗', label: 'Clean Meal', unit: 'meals' },
        'water': { icon: '💧', label: 'Water', unit: 'glasses' },
        'steps': { icon: '👟', label: 'Steps', unit: 'steps' },
        'activity': { icon: '🏋️', label: 'Activity', unit: 'custom' },
    };

    /* ── BMR calculation ── */
    function calculateBMR(profile) {
        const w = parseFloat(profile.weight) || 70;
        const h = parseFloat(profile.height) || 170;
        const a = parseFloat(profile.age) || 30;
        const g = profile.gender || 'm';

        if (g === 'f') {
            return 447.593 + (9.247 * w) + (3.098 * h) - (4.330 * a);
        }
        return 88.362 + (13.397 * w) + (4.799 * h) - (5.677 * a);
    }

    function getMealThreshold(bmr) {
        return Math.round(bmr / 3);
    }

    /* ── Points calculation ── */
    function calculateActivityPoints(activity) {
        if (!activity || activity.type !== 'activity') return 0;
        const storedFactor = parseFloat(activity.factor) || 0;
        const catalogFactor = activitiesCatalog[activity.name]?.factor || 0;
        const factor = storedFactor || catalogFactor;
        return (parseFloat(activity.quantity) || 0) * factor;
    }

    /* ── ISO week + date helpers ── */
    function getIsoWeek(date) {
        const d = new Date(date);
        d.setHours(0, 0, 0, 0);
        d.setDate(d.getDate() + 4 - (d.getDay() || 7));
        const yearStart = new Date(d.getFullYear(), 0, 1);
        const week = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
        return { year: d.getFullYear(), week: week };
    }

    function getWeekDates() {
        const today = new Date();
        const dow = today.getDay() || 7;
        const monday = new Date(today);
        monday.setDate(today.getDate() - dow + 1);
        const dates = [];
        for (let i = 0; i < 7; i++) {
            const d = new Date(monday);
            d.setDate(monday.getDate() + i);
            dates.push(d.toISOString().split('T')[0]);
        }
        return dates;
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
    }

    function formatTime(timestamp) {
        const date = new Date(timestamp);
        if (isNaN(date.getTime())) {
            return timestamp;
        }
        return date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
    }

    /* ── Aggregate entries by date ── */
    function aggregateByDate(entries, dateStr) {
        return entries.filter(e => e.timestamp.substring(0, 10) === dateStr);
    }

    function aggregateWeekData(entries) {
        const result = {};
        const weekDates = getWeekDates();

        weekDates.forEach(d => {
            const dayEntries = aggregateByDate(entries, d);
            result[d] = aggregateDayEntries(dayEntries);
        });

        return result;
    }

    function aggregateDayEntries(entries) {
        const agg = {
            steps: 0,
            water: 0,
            sleep: 0,
            meals: 0,
            activities: [],
            totalPoints: 0,
        };

        entries.forEach(e => {
            switch (e.type) {
                case 'steps':
                    agg.steps += parseFloat(e.quantity) || 0;
                    break;
                case 'water':
                    agg.water += parseFloat(e.quantity) || 0;
                    break;
                case 'sleep':
                    agg.sleep += parseFloat(e.quantity) || 0;
                    break;
                case 'meal':
                    agg.meals += 1;
                    break;
                case 'activity':
                    agg.activities.push(e);
                    agg.totalPoints += calculateActivityPoints(e);
                    break;
            }
        });

        return agg;
    }

    /* ── Load activities catalog ── */
    function loadActivitiesCatalog() {
        return App.fetchJSON('./api/admin.php', { action: 'get_activities' })
            .then(function (data) {
                if (data.activities) {
                    activitiesCatalog = data.activities;
                    populateActivityDropdown();
                }
            })
            .catch(function () { });
    }

    function populateActivityDropdown() {
        const sel = document.getElementById('activity-select');
        if (!sel) return;

        sel.innerHTML = '<option value="">— Select Activity —</option>';
        const categories = {};

        Object.entries(activitiesCatalog).forEach(function ([name, info]) {
            const cat = info.category || 'Other';
            if (!categories[cat]) categories[cat] = [];
            categories[cat].push({ name, ...info });
        });

        Object.keys(categories).sort().forEach(function (cat) {
            const group = document.createElement('optgroup');
            group.label = cat;
            categories[cat].sort(function (a, b) {
                return a.name.localeCompare(b.name);
            }).forEach(function (act) {
                const opt = document.createElement('option');
                opt.value = act.name;
                opt.textContent = act.name + ' (' + act.unit + ')';
                group.appendChild(opt);
            });
            sel.appendChild(group);
        });
    }

    /* ── Load week data ── */
    function loadWeekData() {
        const isoInfo = getIsoWeek(new Date());
        const weekLabel = document.getElementById('week-label');
        if (weekLabel) {
            weekLabel.innerHTML = 'Week <strong>' + isoInfo.week + '</strong>, ' + isoInfo.year;
        }

        return App.fetchJSON('./api/log.php', { action: 'get_week' })
            .then(function (data) {
                if (data.entries && Array.isArray(data.entries)) {
                    weekEntries = data.entries;
                    updateProgressBars();
                    renderWeekTable();
                }
            })
            .catch(function () {
                App.showToast('Could not load week data', 'error');
            });
    }

    /* ── Progress bars ── */
    function updateProgressBars() {
        const weekData = aggregateWeekData(weekEntries);
        const days = Object.values(weekData).filter(d => d && (d.steps || d.water || d.sleep || d.meals || d.activities.length));
        const goals = userGoals;

        // Steps – average
        const stepsTotal = Object.values(weekData).reduce((s, d) => s + (d.steps || 0), 0);
        const stepsAvg = days.length ? stepsTotal / 7 : 0;
        const stepsGoal = parseFloat(goals.avg_steps) || 6000;
        setProgressBar('progress-steps', stepsAvg, stepsGoal,
            Math.round(stepsAvg) + ' avg', Math.round(stepsGoal) + ' goal');

        // Sleep – average
        const sleepTotal = Object.values(weekData).reduce((s, d) => s + (d.sleep || 0), 0);
        const sleepAvg = sleepTotal / 7;
        const sleepGoal = parseFloat(goals.sleep_goal) || 7;
        setProgressBar('progress-sleep', sleepAvg, sleepGoal,
            sleepAvg.toFixed(1) + 'h avg', sleepGoal + 'h goal');

        // Clean meals – total
        const mealsTotal = Object.values(weekData).reduce((s, d) => s + (d.meals || 0), 0);
        const mealsGoal = parseFloat(goals.clean_meals_goal) || 14;
        setProgressBar('progress-meals', mealsTotal, mealsGoal,
            mealsTotal + ' meals', mealsGoal + ' goal');

        // Water – total
        const waterTotal = Object.values(weekData).reduce((s, d) => s + (d.water || 0), 0);
        const waterGoal = (parseFloat(goals.water_goal) || 8) * 7;
        setProgressBar('progress-water', waterTotal, waterGoal,
            waterTotal + ' glasses', Math.round(waterGoal) + ' goal');

        // Activity points
        const totalPoints = Object.values(weekData).reduce((s, d) => s + (d.totalPoints || 0), 0);
        const activityGoal = parseFloat(goals.activity_points_goal) || (parseFloat(goals.workout_hours) || 5) * 60;
        const pct = activityGoal > 0
            ? Math.min(Math.round((totalPoints / activityGoal) * 100), 999)
            : 0;

        const ptsEl = document.getElementById('total-points');
        const pctEl = document.getElementById('points-pct');
        if (ptsEl) ptsEl.textContent = totalPoints.toFixed(1);
        if (pctEl) pctEl.textContent = pct + '% of target';

        setProgressBar('progress-points', totalPoints, activityGoal,
            totalPoints.toFixed(1) + ' pts', Math.round(activityGoal) + ' target');
    }

    function setProgressBar(id, value, goal, valueLbl, goalLbl) {
        const wrapper = document.getElementById(id);
        if (!wrapper) return;
        const fill = wrapper.querySelector('.progress-bar-fill');
        const vLbl = wrapper.querySelector('.progress-value');
        const gLbl = wrapper.querySelector('.progress-goal');

        const pct = goal > 0 ? Math.min((value / goal) * 100, 100) : 0;
        if (fill) fill.style.width = pct + '%';
        if (vLbl) vLbl.textContent = valueLbl;
        if (gLbl) gLbl.textContent = goalLbl;
    }

    /* ── Motivational quote ── */
    function loadMotivationalQuote() {
        fetch('./data/quotes.json')
            .then(function (res) { return res.json(); })
            .then(function (data) {
                const quotes = data.quotes || [];
                if (!quotes.length) return;
                const q = quotes[Math.floor(Math.random() * quotes.length)];
                const el = document.getElementById('motivational-quote');
                if (el) el.textContent = '\u201c' + q + '\u201d';
            })
            .catch(function () { /* silently skip on error */ });
    }

    /* ── Load leaderboard ── */
    function loadLeaderboard() {
        const tbody = document.getElementById('leaderboard-body');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:.5rem">Loading\u2026</td></tr>';
        }
        return App.fetchJSON('./api/log.php', { action: 'get_leaderboard' })
            .then(function (data) {
                if (!tbody) return;
                if (data.error) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-muted)">' + App.escapeHtml(data.error) + '</td></tr>';
                    return;
                }
                const rows = data.leaderboard || [];
                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-muted)">No data yet this week.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (row, idx) {
                    var medal;
                    if (idx === 0)      medal = '\uD83E\uDD47';
                    else if (idx === 1) medal = '\uD83E\uDD48';
                    else if (idx === 2) medal = '\uD83E\uDD49';
                    else                medal = idx + 1;
                    function pctCell(pct) {
                        const capped = Math.min(pct, 999);
                        const color = pct >= 100 ? 'var(--success,#4caf50)' : pct >= 50 ? 'var(--accent)' : 'var(--text-muted)';
                        return '<td style="color:' + color + ';font-weight:600">' + capped + '%</td>';
                    }
                    return '<tr>'
                        + '<td style="text-align:center">' + medal + '</td>'
                        + '<td>' + App.escapeHtml(row.username) + '</td>'
                        + pctCell(row.points_pct)
                        + pctCell(row.steps_pct)
                        + pctCell(row.sleep_pct)
                        + pctCell(row.meals_pct)
                        + pctCell(row.water_pct)
                        + '</tr>';
                }).join('');
            })
            .catch(function () {
                if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-muted)">Could not load leaderboard.</td></tr>';
            });
    }

    /* ── Tab switching ── */
    function initTabs() {
        document.querySelectorAll('.tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const targetId = btn.dataset.tab;
                document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
                document.querySelectorAll('.tab-content').forEach(function (c) { c.classList.remove('active'); });
                btn.classList.add('active');
                const target = document.getElementById(targetId);
                if (target) target.classList.add('active');
                if (targetId === 'leaderboard-tab') loadLeaderboard();
            });
        });
    }


    function renderWeekTable() {
        const tbody = document.getElementById('week-table-body');
        if (!tbody) return;

        const weekData = aggregateWeekData(weekEntries);
        const weekDates = getWeekDates();
        const rows = weekDates.map(date => {
            const agg = weekData[date];
            return `
        <tr>
          <td>${formatDate(date)}</td>
          <td>${agg.steps || 0}</td>
          <td>${agg.sleep || 0}</td>
          <td>${agg.water || 0}</td>
          <td>${agg.meals || 0}</td>
          <td>${(agg.totalPoints || 0).toFixed(1)}</td>
        </tr>`;
        });

        tbody.innerHTML = rows.join('');
    }

    /* ── Render day breakdown (individual entries) ── */
    function renderDetailSummary(dateStr) {
        const summary = document.getElementById('detail-summary');
        if (!summary) return;

        const dayEntries = aggregateByDate(weekEntries, dateStr);

        if (!dayEntries || dayEntries.length === 0) {
            summary.innerHTML = '<p style="color:var(--text-muted);font-size:.85rem">No entries logged for <strong>' + formatDate(dateStr) + '</strong>.</p>';
            return;
        }

        let html = '<div style="font-size:.9rem">';
        html += '<div style="color:var(--text-muted);margin-bottom:.5rem;font-size:.85rem">' + formatDate(dateStr) + ' — ' + dayEntries.length + ' entries</div>';

        dayEntries.forEach(function (entry) {
            const typeInfo = ENTRY_TYPES[entry.type] || {};
            const icon = typeInfo.icon || '📍';
            const time = formatTime(entry.timestamp);
            const note = entry.note ? '<div style="margin-top:.25rem;font-size:.8rem;color:var(--text-muted);font-style:italic">' + App.escapeHtml(entry.note) + '</div>' : '';

            html += '<div class="detail-item">';
            html += '<div>';
            html += '<div class="detail-item-label">' + icon + ' ' + App.escapeHtml(entry.name || typeInfo.label || entry.type) + '</div>';
            html += '<div class="detail-item-value">' + entry.quantity + ' ' + App.escapeHtml(entry.unit || typeInfo.unit || '') + ' @ ' + time + '</div>';
            html += note;
            html += '</div>';
            html += '<div style="display:flex;gap:.25rem">';
            html += '<button class="btn btn-sm btn-secondary" data-entry-id="' + entry.id + '" onclick="window.Dashboard.editEntry(\'' + entry.id + '\')">✏️</button>';
            html += '<button class="btn btn-sm btn-secondary" data-entry-id="' + entry.id + '" onclick="window.Dashboard.deleteEntry(\'' + entry.id + '\')">🗑️</button>';
            html += '</div>';
            html += '</div>';
        });

        html += '</div>';
        summary.innerHTML = html;
    }

    /* ── Add entry ── */
    function addEntry(timestamp, type, quantity, unit, note, name, factor) {
        const entryData = {
            action: 'add_entry',
            timestamp: timestamp,
            type: type,
            quantity: quantity,
            unit: unit,
            note: note,
        };
        
        // Include activity name and factor for activity entries
        if (type === 'activity' && name) {
            entryData.name = name;
            entryData.factor = factor;
        }

        return App.fetchJSON('./api/log.php', entryData)
            .then(function (resp) {
                if (resp.success) {
                    App.showToast('Entry logged!', 'success');
                    loadWeekData();
                    return resp.entry;
                } else {
                    App.showToast(resp.error || 'Failed to log entry', 'error');
                }
            })
            .catch(function () {
                App.showToast('Network error logging entry', 'error');
            });
    }

    function getCurrentTimestamp() {
        const dateVal = document.getElementById('log-date')?.value || new Date().toISOString().split('T')[0];
        const timeVal = document.getElementById('log-time')?.value || '00:00';
        return dateVal + 'T' + (timeVal.length === 5 ? timeVal + ':00' : timeVal);
    }

    function getSharedNote() {
        return document.getElementById('entry-note')?.value || '';
    }

    function logTypedEntry(type, quantity, unit, name, factor) {
        if (!quantity || quantity <= 0) {
            App.showToast('Please enter a valid quantity', 'warning');
            return Promise.resolve();
        }
        const timestamp = getCurrentTimestamp();
        const note = getSharedNote();
        return addEntry(timestamp, type, quantity, unit, note, name, factor)
            .then(function () {
                const detailDate = document.getElementById('detail-date');
                if (detailDate) renderDetailSummary(detailDate.value);
            });
    }

    /* ── Delete entry ── */
    function deleteEntry(entryId) {
        if (!confirm('Delete this entry?')) return;

        return App.fetchJSON('./api/log.php', { action: 'delete_entry', id: entryId })
            .then(function (resp) {
                if (resp.success) {
                    App.showToast('Entry deleted', 'success');
                    loadWeekData();
                    const detailDate = document.getElementById('detail-date');
                    if (detailDate) renderDetailSummary(detailDate.value);
                } else {
                    App.showToast(resp.error || 'Failed to delete', 'error');
                }
            })
            .catch(function () {
                App.showToast('Network error deleting entry', 'error');
            });
    }

    /* ── Edit entry ── */
    function editEntry(entryId) {
        const entry = weekEntries.find(e => e.id === entryId);
        if (!entry) {
            App.showToast('Entry not found', 'error');
            return;
        }

        const newQty = prompt('New quantity:', entry.quantity);
        if (newQty === null) return;

        const newNote = prompt('Note:', entry.note || '');
        if (newNote === null) return;

        const updateData = {
            action: 'edit_entry',
            id: entryId,
            quantity: parseFloat(newQty) || entry.quantity,
            unit: entry.unit,
            note: newNote,
        };

        App.fetchJSON('./api/log.php', updateData)
            .then(function (resp) {
                if (resp.success) {
                    App.showToast('Entry updated', 'success');
                    loadWeekData();
                    const detailDate = document.getElementById('detail-date');
                    if (detailDate) renderDetailSummary(detailDate.value);
                } else {
                    App.showToast(resp.error || 'Failed to update', 'error');
                }
            })
            .catch(function () {
                App.showToast('Network error updating entry', 'error');
            });
    }

    /* ── Init ── */
    function init() {
        // Set today's date
        const today = new Date().toISOString().split('T')[0];

        const logDate = document.getElementById('log-date');
        if (logDate) {
            logDate.value = today;
            logDate.max = today;
        }

        const logTime = document.getElementById('log-time');
        if (logTime) {
            const now = new Date();
            logTime.value = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
        }

        const detailDate = document.getElementById('detail-date');
        if (detailDate) {
            detailDate.value = today;
            detailDate.max = today;
        }

        // Init tab switching
        initTabs();

        // Load motivational quote
        loadMotivationalQuote();

        // Load catalog, then week data
        App.initAuth({
            onLogin: function (data) {
                userGoals = data.goals || {};
                userProfile = data.profile || {};
                const activityGoalInput = document.getElementById('activity-points-goal');
                if (activityGoalInput) {
                    activityGoalInput.value = userGoals.activity_points_goal || '';
                }
                loadActivitiesCatalog().then(function () {
                    loadWeekData().then(function () {
                        if (detailDate) renderDetailSummary(detailDate.value);
                    });
                });

                App.startLogoutTimer();
            }
        });

        const sleepAddBtn = document.getElementById('sleep-add-btn');
        if (sleepAddBtn) {
            sleepAddBtn.addEventListener('click', function () {
                const qtyVal = parseFloat(document.getElementById('sleep-hours')?.value || 0);
                logTypedEntry('sleep', qtyVal, 'hours');
            });
        }

        const mealQuickBtn = document.getElementById('meal-quick-btn');
        if (mealQuickBtn) {
            mealQuickBtn.addEventListener('click', function () {
                const count = document.getElementById('meal-count');
                const current = parseInt(count?.value || '0', 10) || 0;
                count.value = current + 1;
                logTypedEntry('meal', 1, 'meals');
            });
        }

        const mealAddBtn = document.getElementById('meal-add-btn');
        if (mealAddBtn) {
            mealAddBtn.addEventListener('click', function () {
                const qtyVal = parseInt(document.getElementById('meal-count')?.value || '0', 10) || 0;
                logTypedEntry('meal', qtyVal, 'meals');
            });
        }

        const waterQuickBtn = document.getElementById('water-quick-btn');
        if (waterQuickBtn) {
            waterQuickBtn.addEventListener('click', function () {
                const count = document.getElementById('water-count');
                const current = parseFloat(count?.value || '0') || 0;
                count.value = (current + 1).toFixed(1).replace(/\.0$/, '');
                logTypedEntry('water', 1, 'glasses');
            });
        }

        const waterAddBtn = document.getElementById('water-add-btn');
        if (waterAddBtn) {
            waterAddBtn.addEventListener('click', function () {
                const qtyVal = parseFloat(document.getElementById('water-count')?.value || 0);
                logTypedEntry('water', qtyVal, 'glasses');
            });
        }

        const stepsAddBtn = document.getElementById('steps-add-btn');
        if (stepsAddBtn) {
            stepsAddBtn.addEventListener('click', function () {
                const qtyVal = parseInt(document.getElementById('steps-count')?.value || '0', 10) || 0;
                logTypedEntry('steps', qtyVal, 'steps');
            });
        }

        const activityAddBtn = document.getElementById('activity-add-btn');
        if (activityAddBtn) {
            activityAddBtn.addEventListener('click', function () {
                const activitySelect = document.getElementById('activity-select');
                const activityName = activitySelect?.value;
                const quantity = parseFloat(document.getElementById('activity-qty')?.value || 0);
                if (!activityName) {
                    App.showToast('Please select an activity', 'warning');
                    return;
                }
                if (!quantity || quantity <= 0) {
                    App.showToast('Please enter a valid quantity', 'warning');
                    return;
                }
                const catalog = activitiesCatalog[activityName];
                logTypedEntry('activity', quantity, catalog?.unit || '', activityName, catalog?.factor || 0);
            });
        }

        const saveActivityGoalBtn = document.getElementById('save-activity-goal-btn');
        if (saveActivityGoalBtn) {
            saveActivityGoalBtn.addEventListener('click', function () {
                const btn = this;
                const goalInput = document.getElementById('activity-points-goal');
                const goalValue = parseFloat(goalInput?.value || 0);
                btn.disabled = true;
                App.fetchJSON('./api/user.php', {
                    action: 'update_goals',
                    activity_points_goal: goalValue,
                }).then(function (data) {
                    if (data.success) {
                        userGoals.activity_points_goal = goalValue;
                        App.showToast('Activity points goal saved!', 'success');
                        updateProgressBars();
                    } else {
                        App.showToast(data.error || 'Failed to save activity goal', 'error');
                    }
                }).catch(function () {
                    App.showToast('Network error saving activity goal', 'error');
                }).finally(function () {
                    btn.disabled = false;
                });
            });
        }

        // Detail date picker listener
        if (detailDate) {
            detailDate.addEventListener('change', function () {
                renderDetailSummary(this.value);
            });
        }
    }

    /* ── Expose ── */
    window.Dashboard = {
        init: init,
        loadWeekData: loadWeekData,
        loadLeaderboard: loadLeaderboard,
        deleteEntry: deleteEntry,
        editEntry: editEntry,
        calculateActivityPoints: calculateActivityPoints,
        calculateBMR: calculateBMR,
        getMealThreshold: getMealThreshold,
    };

    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
