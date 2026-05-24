<style>
    .commerce-page {
        color: #172033;
    }

    .commerce-breadcrumb {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        margin: 0 0 18px;
        color: #718096;
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .commerce-breadcrumb a {
        color: #2563eb;
    }

    .commerce-hero {
        display: flex;
        justify-content: space-between;
        gap: 18px;
        align-items: flex-start;
        margin-bottom: 18px;
        padding: 26px;
        border-radius: 8px;
        background: #0f172a;
        color: #ffffff;
        box-shadow: 0 18px 42px rgba(15, 23, 42, .18);
    }

    .commerce-hero__label {
        margin-bottom: 8px;
        color: #67e8f9;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .14em;
        text-transform: uppercase;
    }

    .commerce-hero h1 {
        margin: 0;
        color: #ffffff;
        font-size: 28px;
        font-weight: 800;
        line-height: 1.2;
    }

    .commerce-hero p {
        max-width: 760px;
        margin: 8px 0 0;
        color: #cbd5e1;
        font-size: 15px;
    }

    .commerce-hero__actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 10px;
    }

    .commerce-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .commerce-stats.three {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .commerce-stat {
        min-height: 112px;
        padding: 18px;
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        background: #ffffff;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .06);
    }

    .commerce-stat span {
        display: block;
        margin-bottom: 10px;
        color: #718096;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .commerce-stat strong {
        display: block;
        color: #0f172a;
        font-size: 24px;
        font-weight: 800;
        line-height: 1.15;
    }

    .commerce-stat small {
        display: block;
        margin-top: 8px;
        color: #64748b;
        font-size: 12px;
    }

    .commerce-panel {
        margin-bottom: 18px;
        padding: 22px;
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        background: #ffffff;
        box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
    }

    .commerce-panel__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
    }

    .commerce-panel__title {
        margin: 0;
        color: #0f172a;
        font-size: 20px;
        font-weight: 800;
    }

    .commerce-panel__subtitle {
        margin: 5px 0 0;
        color: #64748b;
        font-size: 14px;
    }

    .commerce-table {
        width: 100%;
    }

    .commerce-table thead th {
        border-color: #1e293b !important;
        background: #111827;
        color: #ffffff;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .commerce-table tbody td,
    .commerce-table tbody th {
        vertical-align: middle;
        color: #223047;
        border-color: #e5edf6;
    }

    .commerce-table tbody tr:hover {
        background: #f8fbff;
    }

    .commerce-thumb {
        width: 64px;
        height: 64px;
        border-radius: 8px;
        border: 1px solid #dbe3ef;
        object-fit: cover;
        background: #f8fafc;
    }

    .commerce-proof-link {
        position: relative;
        display: inline-flex;
        width: 64px;
        height: 64px;
        border-radius: 8px;
        overflow: hidden;
    }

    .commerce-proof-link .commerce-thumb {
        width: 64px;
        height: 64px;
    }

    .commerce-proof-badge {
        position: absolute;
        right: 5px;
        bottom: 5px;
        padding: 3px 6px;
        border-radius: 999px;
        background: rgba(15, 23, 42, .86);
        color: #ffffff;
        font-size: 10px;
        font-weight: 900;
        line-height: 1;
    }

    .commerce-product-name {
        max-width: 300px;
        color: #0f172a;
        font-weight: 800;
        line-height: 1.35;
    }

    .commerce-muted {
        color: #64748b;
        font-size: 13px;
    }

    .commerce-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 88px;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 800;
        line-height: 1;
        white-space: nowrap;
    }

    .status-pending,
    .status-processing {
        background: #eef2f7;
        color: #475569;
    }

    .status-approved,
    .status-confirmed,
    .status-published,
    .status-completed {
        background: #dcfce7;
        color: #166534;
    }

    .status-delivery {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .status-rejected,
    .status-out {
        background: #fee2e2;
        color: #b91c1c;
    }

    .status-withdrawn {
        background: #e0f2fe;
        color: #0369a1;
    }

    .commerce-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 7px;
    }

    .commerce-actions .btn,
    .commerce-hero__actions .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border-radius: 8px;
        font-weight: 800;
        box-shadow: none;
    }

    .commerce-icon-btn {
        width: 36px;
        height: 36px;
        padding: 0;
    }

    .commerce-empty {
        padding: 40px 18px;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        background: #f8fafc;
        text-align: center;
        color: #64748b;
        font-weight: 700;
    }

    .commerce-form-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 340px;
        gap: 22px;
        align-items: start;
    }

    .commerce-form-section {
        display: grid;
        gap: 16px;
    }

    .commerce-form-section label {
        color: #334155;
        font-weight: 800;
    }

    .commerce-form-section .form-control,
    .commerce-form-section .form-select {
        min-height: 46px;
        border-color: #d7e0ea;
        border-radius: 8px;
        color: #0f172a;
    }

    .commerce-preview {
        position: sticky;
        top: 90px;
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        background: #f8fafc;
        overflow: hidden;
    }

    .commerce-preview__image {
        width: 100%;
        aspect-ratio: 1 / 1;
        object-fit: cover;
        background: #e2e8f0;
    }

    .commerce-preview__body {
        padding: 16px;
    }

    .commerce-preview__body strong {
        display: block;
        color: #0f172a;
        font-size: 16px;
        font-weight: 800;
    }

    .commerce-help-list {
        margin: 12px 0 0;
        padding-left: 18px;
        color: #64748b;
        font-size: 13px;
    }

    .commerce-catalogue-toolbar {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        margin-bottom: 18px;
    }

    .commerce-cart-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        background: #eff6ff;
        color: #1d4ed8;
        font-weight: 800;
    }

    .commerce-catalogue-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
        gap: 18px;
    }

    .commerce-product-card {
        display: flex;
        flex-direction: column;
        min-height: 100%;
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        background: #ffffff;
        overflow: hidden;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .06);
    }

    .commerce-product-card__image {
        position: relative;
        display: block;
        background: #f1f5f9;
    }

    .commerce-product-card__image img {
        width: 100%;
        aspect-ratio: 4 / 3;
        object-fit: cover;
    }

    .commerce-product-card__body {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: 12px;
        padding: 16px;
    }

    .commerce-product-card h3 {
        margin: 0;
        color: #0f172a;
        font-size: 17px;
        font-weight: 800;
        line-height: 1.35;
    }

    .commerce-product-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        color: #64748b;
        font-size: 13px;
    }

    .commerce-product-meta span {
        padding: 5px 8px;
        border-radius: 999px;
        background: #f1f5f9;
    }

    .quantity-control {
        display: inline-grid;
        grid-template-columns: 38px 64px 38px;
        height: 40px;
        border: 1px solid #dbe3ef;
        border-radius: 8px;
        overflow: hidden;
    }

    .quantity-control button,
    .quantity-control input {
        border: 0;
        background: #ffffff;
        text-align: center;
        font-weight: 800;
    }

    .quantity-control button {
        color: #0f172a;
    }

    .quantity-control input {
        border-right: 1px solid #dbe3ef;
        border-left: 1px solid #dbe3ef;
    }

    .commerce-alert {
        border-radius: 8px;
        border: 1px solid transparent;
        font-weight: 700;
    }

    .commerce-order-items {
        display: grid;
        gap: 12px;
    }

    .commerce-order-item {
        display: grid;
        grid-template-columns: 72px minmax(0, 1fr);
        gap: 12px;
        align-items: center;
        padding: 12px;
        border: 1px solid #e5edf6;
        border-radius: 8px;
        background: #f8fafc;
    }

    .commerce-order-item img {
        width: 72px;
        height: 72px;
        border-radius: 8px;
        object-fit: cover;
        background: #e2e8f0;
    }

    @media (max-width: 1199px) {
        .commerce-stats,
        .commerce-stats.three {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .commerce-form-grid {
            grid-template-columns: 1fr;
        }

        .commerce-preview {
            position: static;
        }
    }

    @media (max-width: 767px) {
        .commerce-hero,
        .commerce-panel__header,
        .commerce-catalogue-toolbar {
            flex-direction: column;
        }

        .commerce-hero__actions,
        .commerce-actions {
            justify-content: flex-start;
        }

        .commerce-stats,
        .commerce-stats.three {
            grid-template-columns: 1fr;
        }

        .commerce-hero h1 {
            font-size: 24px;
        }
    }
</style>
