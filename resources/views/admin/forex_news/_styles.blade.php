<style>
    .news-admin {
        color: #172033;
    }

    .news-hero {
        align-items: center;
        background: #0f172a;
        border: 1px solid #243047;
        border-radius: 10px;
        color: #f8fafc;
        display: flex;
        justify-content: space-between;
        gap: 18px;
        padding: 26px 28px;
    }

    .news-hero .eyebrow {
        color: #67e8f9;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 0;
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .news-hero h1 {
        color: #fff;
        font-size: 30px;
        font-weight: 800;
        letter-spacing: 0;
        margin: 0 0 7px;
    }

    .news-hero p {
        color: #cbd5e1;
        margin: 0;
        max-width: 760px;
    }

    .news-panel,
    .news-stat {
        background: #fff;
        border: 1px solid #d8e0ea;
        border-radius: 10px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .news-panel {
        padding: 22px;
    }

    .news-stat {
        height: 100%;
        padding: 18px;
    }

    .news-stat span,
    .news-muted {
        color: #617188;
        font-size: 13px;
    }

    .news-stat strong {
        color: #101827;
        display: block;
        font-size: 25px;
        line-height: 1.15;
        margin-top: 6px;
    }

    .news-table {
        border-collapse: separate;
        border-spacing: 0;
        width: 100%;
    }

    .news-table thead th {
        background: #0f172a;
        border-color: #27344d;
        color: #fff;
        font-size: 12px;
        letter-spacing: 0;
        padding: 14px 12px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .news-table tbody td {
        border-color: #e2e8f0;
        padding: 14px 12px;
        vertical-align: middle;
    }

    .news-brief {
        min-width: 310px;
    }

    .news-brief strong {
        color: #111827;
        display: block;
        font-size: 15px;
    }

    .news-brief p {
        color: #64748b;
        display: -webkit-box;
        font-size: 13px;
        margin: 5px 0 0;
        max-width: 540px;
        overflow: hidden;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .impact-pill,
    .status-pill {
        align-items: center;
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        gap: 6px;
        padding: 6px 10px;
        white-space: nowrap;
    }

    .impact-low {
        background: #e8f8ef;
        color: #147a3f;
    }

    .impact-medium {
        background: #fff4d6;
        color: #9a5a00;
    }

    .impact-high {
        background: #fee2e2;
        color: #b42318;
    }

    .status-live {
        background: #e0f2fe;
        color: #0369a1;
    }

    .status-draft {
        background: #eef2f7;
        color: #475569;
    }

    .news-thumb {
        align-items: center;
        background: #f8fafc;
        border: 1px solid #d8e0ea;
        border-radius: 8px;
        color: #8a99ad;
        display: inline-flex;
        height: 64px;
        justify-content: center;
        overflow: hidden;
        width: 86px;
    }

    .news-thumb img {
        height: 100%;
        object-fit: cover;
        width: 100%;
    }

    .news-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        justify-content: flex-end;
        min-width: 260px;
    }

    .news-actions .btn {
        align-items: center;
        display: inline-flex;
        gap: 5px;
        justify-content: center;
    }

    .news-form-grid {
        display: grid;
        gap: 22px;
        grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.8fr);
    }

    .news-field {
        margin-bottom: 18px;
    }

    .news-field label {
        color: #334155;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .news-field .form-control,
    .news-field .form-select {
        border-color: #d8e0ea;
        border-radius: 8px;
        min-height: 46px;
    }

    .news-preview-image {
        align-items: center;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        color: #64748b;
        display: flex;
        min-height: 220px;
        justify-content: center;
        overflow: hidden;
        text-align: center;
    }

    .news-preview-image img {
        height: 100%;
        max-height: 260px;
        object-fit: cover;
        width: 100%;
    }

    .news-preview-copy {
        background: #0f172a;
        border-radius: 10px;
        color: #dbeafe;
        font-family: Consolas, Monaco, monospace;
        font-size: 13px;
        line-height: 1.65;
        min-height: 210px;
        padding: 16px;
        white-space: pre-wrap;
    }

    .news-detail-hero {
        background: #0f172a;
        border-radius: 10px;
        color: #fff;
        overflow: hidden;
    }

    .news-detail-media {
        background: #f8fafc;
        min-height: 320px;
    }

    .news-detail-media img {
        height: 100%;
        min-height: 320px;
        object-fit: cover;
        width: 100%;
    }

    .news-detail-body {
        padding: 28px;
    }

    .news-detail-body h1 {
        color: #fff;
        font-size: 30px;
        font-weight: 800;
        letter-spacing: 0;
    }

    .news-detail-body p {
        color: #cbd5e1;
    }

    .news-empty {
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        color: #64748b;
        padding: 42px 18px;
        text-align: center;
    }

    @media (max-width: 1100px) {
        .news-form-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .news-hero {
            align-items: flex-start;
            flex-direction: column;
        }

        .news-hero h1,
        .news-detail-body h1 {
            font-size: 25px;
        }

        .news-actions {
            justify-content: flex-start;
            min-width: 0;
        }
    }
</style>
