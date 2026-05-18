<style>
    .ops-admin {
        --ops-ink: #10203a;
        --ops-muted: #5f6f86;
        --ops-border: #d8e0ec;
        --ops-soft: #f5f8fc;
        --ops-panel: #ffffff;
        --ops-navy: #13213a;
        --ops-navy-2: #1d2d4a;
        --ops-blue: #0f84d8;
        --ops-teal: #0f9f8f;
        --ops-green: #26a269;
        --ops-amber: #b7791f;
        --ops-red: #d64545;
        color: var(--ops-ink);
    }

    .ops-admin .page-title-box h4,
    .ops-admin h1,
    .ops-admin h2,
    .ops-admin h3,
    .ops-admin h4,
    .ops-admin h5,
    .ops-admin h6 {
        color: var(--ops-ink);
        letter-spacing: 0;
    }

    .ops-titlebar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
    }

    .ops-titlebar .breadcrumb {
        background: transparent;
        margin: 0;
        padding: 0;
        color: var(--ops-muted);
    }

    .ops-hero {
        border: 1px solid #263a57;
        background: linear-gradient(135deg, #101a2f 0%, #1f304e 64%, #18375d 100%);
        border-radius: 8px;
        color: #ffffff;
        padding: 24px;
        box-shadow: 0 18px 42px rgba(16, 32, 58, .16);
        margin-bottom: 18px;
    }

    .ops-hero h3,
    .ops-hero h4 {
        color: #ffffff;
        margin-bottom: 8px;
    }

    .ops-hero p,
    .ops-hero .ops-muted,
    .ops-hero .ops-eyebrow {
        color: #cbd6e6;
    }

    .ops-eyebrow {
        color: var(--ops-muted);
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .ops-muted {
        color: var(--ops-muted);
    }

    .ops-action-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
    }

    .ops-stat-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .ops-stat {
        background: var(--ops-panel);
        border: 1px solid var(--ops-border);
        border-radius: 8px;
        padding: 16px;
        min-height: 118px;
        box-shadow: 0 10px 26px rgba(16, 32, 58, .07);
    }

    .ops-stat span {
        display: block;
        color: var(--ops-muted);
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .ops-stat strong {
        display: block;
        color: var(--ops-ink);
        font-size: 28px;
        font-weight: 800;
        line-height: 1;
    }

    .ops-stat small {
        display: block;
        color: var(--ops-muted);
        margin-top: 10px;
    }

    .ops-panel {
        background: var(--ops-panel);
        border: 1px solid var(--ops-border);
        border-radius: 8px;
        box-shadow: 0 12px 32px rgba(16, 32, 58, .08);
        margin-bottom: 18px;
    }

    .ops-panel-header {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 14px;
        padding: 18px 20px;
        border-bottom: 1px solid var(--ops-border);
    }

    .ops-panel-body {
        padding: 20px;
    }

    .ops-table {
        margin-bottom: 0;
    }

    .ops-table thead th {
        background: #0f1d33;
        border-color: #243853;
        color: #ffffff;
        font-size: 12px;
        letter-spacing: .04em;
        text-transform: uppercase;
        vertical-align: middle;
    }

    .ops-table td {
        border-color: #edf1f6;
        color: var(--ops-ink);
        vertical-align: middle;
    }

    .ops-table tbody tr:hover {
        background: #f6f9fd;
    }

    .ops-code {
        background: #eef4fb;
        border: 1px solid #d8e6f6;
        border-radius: 6px;
        color: #17365d;
        display: inline-flex;
        font-family: Consolas, Monaco, monospace;
        font-size: 12px;
        padding: 4px 8px;
    }

    .ops-badge {
        align-items: center;
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        gap: 6px;
        padding: 6px 10px;
        white-space: nowrap;
    }

    .ops-badge-success {
        background: #dcf7ea;
        color: #11613d;
    }

    .ops-badge-muted {
        background: #edf2f7;
        color: #4b5c72;
    }

    .ops-badge-info {
        background: #e3f2ff;
        color: #0b5798;
    }

    .ops-badge-warning {
        background: #fff4d8;
        color: #805514;
    }

    .ops-badge-danger {
        background: #fde2e2;
        color: #9c2f2f;
    }

    .ops-icon {
        align-items: center;
        background: #e8f5ff;
        border-radius: 8px;
        color: var(--ops-blue);
        display: inline-flex;
        flex: 0 0 auto;
        font-size: 22px;
        height: 44px;
        justify-content: center;
        width: 44px;
    }

    .ops-icon-success {
        background: #dff7eb;
        color: var(--ops-green);
    }

    .ops-icon-warning {
        background: #fff3d6;
        color: var(--ops-amber);
    }

    .ops-icon-teal {
        background: #dcf7f4;
        color: var(--ops-teal);
    }

    .ops-actions {
        display: inline-flex;
        justify-content: center;
        gap: 8px;
    }

    .ops-actions .btn,
    .ops-action-row .btn,
    .ops-panel-header .btn,
    .ops-hero .btn {
        float: none;
        padding: .46rem .75rem;
    }

    .ops-form-shell {
        max-width: 1040px;
    }

    .ops-form-grid {
        display: grid;
        gap: 16px;
    }

    .ops-field {
        display: grid;
        grid-template-columns: 220px minmax(0, 1fr);
        gap: 18px;
        align-items: flex-start;
    }

    .ops-field label {
        color: var(--ops-ink);
        font-weight: 800;
        margin-top: 9px;
    }

    .ops-field .form-control {
        border-color: #cdd8e6;
        color: var(--ops-ink);
        min-height: 42px;
    }

    .ops-field .form-control:focus {
        border-color: var(--ops-blue);
        box-shadow: 0 0 0 .15rem rgba(15, 132, 216, .14);
    }

    .ops-help {
        color: var(--ops-muted);
        font-size: 12px;
        margin-top: 6px;
    }

    .ops-switch {
        align-items: center;
        display: inline-flex;
        gap: 10px;
        min-height: 42px;
    }

    .ops-switch input {
        height: 18px;
        width: 18px;
    }

    .ops-module-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 18px;
    }

    .ops-module {
        background: var(--ops-panel);
        border: 1px solid var(--ops-border);
        border-radius: 8px;
        box-shadow: 0 12px 30px rgba(16, 32, 58, .08);
        padding: 20px;
    }

    .ops-module p {
        color: var(--ops-muted);
    }

    .ops-module-top {
        align-items: flex-start;
        display: flex;
        gap: 14px;
        justify-content: space-between;
    }

    .ops-module-copy {
        align-items: flex-start;
        display: flex;
        gap: 14px;
        min-width: 0;
    }

    .ops-feature-list {
        display: grid;
        gap: 10px;
        margin-top: 15px;
    }

    .ops-feature-row {
        align-items: center;
        background: var(--ops-soft);
        border: 1px solid #e4eaf3;
        border-radius: 8px;
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 14px;
    }

    .ops-empty {
        background: var(--ops-soft);
        border: 1px dashed #c9d5e5;
        border-radius: 8px;
        color: var(--ops-muted);
        padding: 24px;
        text-align: center;
    }

    @media (max-width: 1199px) {
        .ops-stat-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 991px) {
        .ops-module-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767px) {
        .ops-titlebar,
        .ops-panel-header,
        .ops-module-top {
            display: block;
        }

        .ops-action-row {
            justify-content: flex-start;
            margin-top: 12px;
        }

        .ops-stat-grid {
            grid-template-columns: 1fr;
        }

        .ops-field {
            grid-template-columns: 1fr;
            gap: 6px;
        }
    }
</style>
