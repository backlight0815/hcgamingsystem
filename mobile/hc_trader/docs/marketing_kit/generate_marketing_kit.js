const fs = require('fs');
const path = require('path');
const zlib = require('zlib');

const outDir = __dirname;
const generatedDate = '2026-06-28';
const projectName = 'mobile/hc_trader';

const app = {
  name: 'HC Trader',
  title: 'App Marketing Overview',
  tagline: 'A complete trading operations platform for teams, traders and growth workflows.',
  positioning: 'HC Trader brings secure access, trader workflows, education content, operational management and team growth tools into one streamlined mobile experience.',
  value: 'Designed for trading businesses that need a cleaner way to manage users, support traders, publish market content, track performance and scale referral-led growth.',
};

const highlights = [
  {
    icon: 'users',
    title: 'Multi-role operations',
    text: 'Built for admins, traders, recruiters, analysts and signal teams.',
  },
  {
    icon: 'chart',
    title: 'Journal and insights',
    text: 'Track trades, review results and turn activity into performance visibility.',
  },
  {
    icon: 'signal',
    title: 'Trading hub',
    text: 'Central space for signals, analysis, learning content, exams and resources.',
  },
  {
    icon: 'shield',
    title: 'Verification and control',
    text: 'Support onboarding, account review, role management and operational governance.',
  },
  {
    icon: 'network',
    title: 'Referral growth engine',
    text: 'Invite traders and lead clients through structured downline workflows.',
  },
  {
    icon: 'globe',
    title: 'Bilingual experience',
    text: 'English and Chinese UI support with dynamic content translation tooling.',
  },
];

const modules = [
  ['Secure onboarding', 'Sign in, registration, password reset and referral-aware account creation.'],
  ['Admin command center', 'Manage users, modules, reviews, content, features, audit logs and competitions.'],
  ['Trading journal', 'Capture trades, attachments, imports and performance history in one place.'],
  ['Dashboard and reports', 'Give traders and teams clearer visibility into trading progress.'],
  ['Trading hub', 'Deliver market analysis, signals, blogs, recordings, knowledge and resources.'],
  ['Prop firm support', 'Track phases, risk limits, targets, reviews and leaderboard readiness.'],
  ['Readiness checklist', 'Guide traders through preparation before live or funded progression.'],
  ['Support desk', 'Centralize tickets, conversations, attachments and staff follow-up.'],
  ['Appointments', 'Coordinate mentor or trading support sessions through bookings and approvals.'],
  ['Recruitment network', 'Support referral codes, invites and downline visibility.'],
  ['Profile and settings', 'Let users manage details, language, notifications and security.'],
  ['Notifications', 'Keep users informed about signals, content, appointments, tickets and exam results.'],
];

const userGroups = [
  {
    name: 'Admin teams',
    needs: 'Control users, modules, verification, content and operational workflows.',
    help: 'HC Trader gives admins a central workspace for oversight, review queues and feature control.',
  },
  {
    name: 'Trading leaders and recruiters',
    needs: 'Grow trader networks while keeping invitation paths organized.',
    help: 'Referral and downline features support structured growth without losing visibility.',
  },
  {
    name: 'Traders and lead clients',
    needs: 'Track progress, access resources, complete exams and get support.',
    help: 'The app combines journal tools, education content, account workflows and support in one mobile flow.',
  },
  {
    name: 'Market analysts and signal teams',
    needs: 'Publish timely trading content to the right audience.',
    help: 'Publisher workspaces support market analysis and signal delivery with notification support.',
  },
  {
    name: 'Support and operations staff',
    needs: 'Handle verification, tickets, appointments and workflow follow-up.',
    help: 'Support, appointment and admin review modules make service activity easier to track.',
  },
];

const benefits = [
  'Centralized operations across users, trading workflows, content and support.',
  'Improved trader experience through one mobile workspace instead of scattered tools.',
  'Better visibility into journals, progress, exams, prop firm readiness and performance.',
  'Scalable growth support through referral codes, invite flows and downline records.',
  'Cleaner team coordination with role-aware administration and publisher workspaces.',
  'Multilingual accessibility for English and Chinese users.',
  'Stronger follow-up through notifications, ticket updates and appointment workflows.',
  'More organized governance through feature toggles, audit logs and review queues.',
];

const journeys = [
  ['Getting started', 'A new user registers or signs in, selects language and enters the app through secure account access.'],
  ['Trader experience', 'Traders use the dashboard, journal, trading hub, tools, exams, accounts, support and appointments to manage daily progress.'],
  ['Admin operation', 'Admins review users, verification, positions, content, features, performance and audit activity from a central command center.'],
  ['Publisher workflow', 'Market analysts and signal providers publish timely content that can be surfaced in the trading hub and notifications.'],
  ['Growth workflow', 'Recruiters, leaders and eligible users use referral codes and downline views to support organized expansion.'],
  ['Support workflow', 'Users submit tickets or appointment requests while staff can respond, review and manage follow-up.'],
];

const confirmedFeatures = [
  'Firebase-backed secure account access',
  'Email/username sign-in and registration',
  'Referral-aware registration and downline records',
  'Multi-role user structure',
  'Admin dashboard and resource management',
  'Feature toggles and audit logs',
  'Trading journal with import and attachments',
  'Dashboard and performance analytics',
  'Trading accounts and capital tracking',
  'Verification/onboarding review workflow',
  'Trading hub for analysis, signals, blogs, recordings, knowledge and resources',
  'Daily exam and final examination workflows',
  'Risk calculator, statistics, psychology and trading plan tools',
  'Prop firm progress tools and admin review support',
  'Readiness checklist',
  'Support tickets with messages and attachments',
  'Appointment booking and review workflow',
  'Recruitment/invite workspace',
  'Profile, avatar, password and settings management',
  'English and Chinese localization',
  'Push notification preferences and FCM topic/direct notifications',
  'Android, Web and Windows project support',
];

const requiresConfirmation = [
  'AI assistant: an ai_assistant feature flag is present, but no confirmed Flutter user-facing AI assistant module was found.',
  'Financial Department role: requested as a possible role, but no implemented Flutter role id or screen was confirmed.',
  'iOS, macOS and Linux builds: dependencies may be portable, but project folders are not present in this snapshot.',
];

function iconSvg(name) {
  const common = 'fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"';
  const icons = {
    users: `<svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="18" cy="17" r="6" ${common}/><circle cx="32" cy="19" r="5" ${common}/><path d="M7 38c2.2-7 7.2-10.5 15-10.5S34.8 31 37 38" ${common}/><path d="M28 29c5.5.3 9.5 3.3 12 9" ${common}/></svg>`,
    chart: `<svg viewBox="0 0 48 48" aria-hidden="true"><path d="M8 39h32" ${common}/><path d="M12 34V22" ${common}/><path d="M23 34V14" ${common}/><path d="M34 34V19" ${common}/><path d="M10 25l10-8 8 7 10-12" ${common}/></svg>`,
    signal: `<svg viewBox="0 0 48 48" aria-hidden="true"><path d="M8 34h6l5-17 7 24 6-18 4 11h4" ${common}/><path d="M10 12h28" ${common}/></svg>`,
    shield: `<svg viewBox="0 0 48 48" aria-hidden="true"><path d="M24 5l16 6v11c0 10-6.5 17.5-16 21-9.5-3.5-16-11-16-21V11l16-6z" ${common}/><path d="M17 24l5 5 10-11" ${common}/></svg>`,
    network: `<svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="9" r="5" ${common}/><circle cx="10" cy="35" r="5" ${common}/><circle cx="38" cy="35" r="5" ${common}/><path d="M21 13L12 31M27 13l9 18M15 35h18" ${common}/></svg>`,
    globe: `<svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="18" ${common}/><path d="M6 24h36M24 6c5 5 7 11 7 18s-2 13-7 18c-5-5-7-11-7-18s2-13 7-18z" ${common}/></svg>`,
    support: `<svg viewBox="0 0 48 48" aria-hidden="true"><path d="M13 27v-6a11 11 0 0 1 22 0v6" ${common}/><path d="M12 27h6v10h-6zM30 27h6v10h-6z" ${common}/><path d="M30 38c-2 2-4 3-7 3h-4" ${common}/></svg>`,
  };
  return icons[name] || icons.signal;
}

function posterHtml() {
  const highlightCards = highlights.map((item) => `
    <article class="highlight-card">
      <div class="icon">${iconSvg(item.icon)}</div>
      <h3>${escapeHtml(item.title)}</h3>
      <p>${escapeHtml(item.text)}</p>
    </article>`).join('');
  const posterModules = [
    ['Secure onboarding', 'Sign in, registration, password reset and referral-aware account creation.'],
    ['Admin command center', 'Manage users, modules, reviews, content, features, audit logs and competitions.'],
    ['Trading journal', 'Capture trades, attachments, imports and performance history in one place.'],
    ['Dashboard and reports', 'Give traders and teams clearer visibility into trading progress.'],
    ['Trading hub', 'Deliver market analysis, signals, blogs, recordings, knowledge and resources.'],
    ['Prop firm support', 'Track phases, risk limits, targets, reviews and leaderboard readiness.'],
    ['Readiness checklist', 'Guide traders through preparation before live or funded progression.'],
    ['Support and growth', 'Centralize tickets, appointments, referrals and downline visibility.'],
  ];
  const moduleItems = posterModules.map(([name, text]) => `
    <div class="module-row">
      <strong>${escapeHtml(name)}</strong>
      <span>${escapeHtml(text)}</span>
    </div>`).join('');
  const groupItems = userGroups.map((group) => `
    <div class="segment">
      <span>${escapeHtml(group.name)}</span>
    </div>`).join('');
  const benefitItems = benefits.slice(0, 6).map((item) => `<li>${escapeHtml(item)}</li>`).join('');

  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HC Trader Marketing Poster</title>
  <style>
    :root {
      --navy: #09212f;
      --deep: #0f3440;
      --teal: #00796b;
      --mint: #8de8d7;
      --gold: #d99a17;
      --paper: #f6faf8;
      --ink: #10212a;
      --muted: #5a6b73;
      --line: rgba(13, 46, 57, .12);
      --card: rgba(255, 255, 255, .92);
    }
    * { box-sizing: border-box; }
    body { margin: 0; background: #dfe8e6; font-family: Inter, Aptos, Arial, sans-serif; color: var(--ink); }
    .poster {
      width: 1600px;
      height: 2600px;
      margin: 0;
      background: var(--paper);
      position: relative;
      overflow: hidden;
    }
    .top-band {
      position: absolute;
      inset: 0 0 auto 0;
      height: 900px;
      background: linear-gradient(135deg, var(--navy), var(--deep) 60%, #165d56);
      color: #fff;
      padding: 78px 88px 0;
    }
    .brand-row { display: flex; align-items: center; justify-content: space-between; gap: 40px; }
    .brand { display: flex; align-items: center; gap: 20px; font-weight: 850; letter-spacing: .3px; font-size: 28px; }
    .logo-mark {
      width: 62px; height: 62px; border-radius: 18px;
      background: linear-gradient(135deg, var(--mint), #31b6a4);
      display: grid; place-items: center; color: var(--navy); font-weight: 900; font-size: 24px;
    }
    .badge {
      border: 1px solid rgba(255,255,255,.24);
      background: rgba(255,255,255,.1);
      padding: 12px 18px;
      border-radius: 999px;
      color: rgba(255,255,255,.88);
      font-size: 18px;
      font-weight: 700;
    }
    .hero {
      display: grid;
      grid-template-columns: 1fr 440px;
      gap: 54px;
      margin-top: 68px;
      align-items: start;
    }
    .eyebrow { color: var(--mint); font-weight: 850; text-transform: uppercase; letter-spacing: 2.2px; font-size: 18px; margin-bottom: 18px; }
    h1 { margin: 0; font-size: 82px; line-height: .96; letter-spacing: -1.5px; max-width: 860px; }
    .hero p { font-size: 29px; line-height: 1.35; color: rgba(255,255,255,.84); max-width: 880px; margin: 28px 0 0; }
    .hero-stats { display: flex; gap: 18px; margin-top: 42px; }
    .stat {
      min-width: 145px;
      padding: 18px 20px;
      border-radius: 20px;
      background: rgba(255,255,255,.1);
      border: 1px solid rgba(255,255,255,.16);
    }
    .stat strong { display: block; font-size: 34px; color: #fff; }
    .stat span { display: block; color: rgba(255,255,255,.72); font-size: 15px; margin-top: 3px; }
    .phone {
      height: 650px;
      border-radius: 54px;
      background: #07171f;
      padding: 22px;
      border: 8px solid rgba(255,255,255,.2);
      box-shadow: 0 34px 70px rgba(0,0,0,.28);
    }
    .phone-screen {
      height: 100%;
      border-radius: 38px;
      background: #10171d;
      color: #e8f4f1;
      padding: 34px 28px;
      overflow: hidden;
    }
    .screen-title { font-size: 22px; font-weight: 850; margin-bottom: 8px; }
    .screen-sub { color: #9fb3b0; font-size: 15px; margin-bottom: 26px; }
    .metric-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .metric { background: #18232a; border: 1px solid #2b3a42; border-radius: 20px; padding: 18px; }
    .metric b { display: block; color: var(--mint); font-size: 24px; margin-bottom: 6px; }
    .metric span { color: #c8d5d2; font-size: 13px; }
    .activity { margin-top: 24px; display: grid; gap: 12px; }
    .activity div { background: #152027; border-radius: 16px; padding: 15px 16px; border-left: 5px solid var(--mint); font-size: 14px; color: #d7e6e3; }
    .tabs { position: absolute; top: 818px; left: 1127px; width: 338px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
    .tabs span { background: #16232b; color: #bcd1cd; border-radius: 14px; padding: 10px 0; text-align: center; font-size: 11px; }
    .content { position: absolute; inset: 900px 0 0 0; padding: 86px 88px 94px; }
    .section-head { display: flex; align-items: end; justify-content: space-between; gap: 32px; margin-bottom: 30px; }
    .section-head h2 { font-size: 40px; margin: 0; letter-spacing: -.4px; color: var(--navy); }
    .section-head p { margin: 0; color: var(--muted); font-size: 20px; max-width: 560px; line-height: 1.35; }
    .highlights { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
    .highlight-card {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 28px;
      padding: 28px;
      min-height: 220px;
      box-shadow: 0 12px 30px rgba(8, 36, 43, .08);
    }
    .icon { width: 58px; height: 58px; border-radius: 18px; display: grid; place-items: center; background: #e7f8f4; color: var(--teal); margin-bottom: 22px; }
    .icon svg { width: 36px; height: 36px; }
    .highlight-card h3 { margin: 0 0 10px; font-size: 24px; color: var(--navy); }
    .highlight-card p { margin: 0; color: var(--muted); font-size: 18px; line-height: 1.34; }
    .lower { display: grid; grid-template-columns: 1.05fr .95fr; gap: 34px; margin-top: 56px; }
    .panel {
      background: #fff;
      border: 1px solid var(--line);
      border-radius: 34px;
      padding: 34px;
      box-shadow: 0 14px 36px rgba(8, 36, 43, .08);
    }
    .panel h2 { margin: 0 0 22px; color: var(--navy); font-size: 33px; }
    .module-row {
      display: grid;
      grid-template-columns: 230px 1fr;
      gap: 18px;
      padding: 18px 0;
      border-top: 1px solid #edf1ef;
      align-items: start;
    }
    .module-row:first-of-type { border-top: 0; padding-top: 0; }
    .module-row strong { font-size: 19px; color: var(--teal); }
    .module-row span { color: var(--muted); font-size: 18px; line-height: 1.34; }
    .segments { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 30px; }
    .segment { border-radius: 999px; padding: 12px 17px; background: #f0f7f5; color: var(--deep); border: 1px solid #d8ece7; font-weight: 800; font-size: 17px; }
    .benefits { list-style: none; padding: 0; margin: 0; display: grid; gap: 15px; }
    .benefits li {
      position: relative;
      padding-left: 34px;
      font-size: 18px;
      line-height: 1.35;
      color: var(--muted);
    }
    .benefits li:before {
      content: "";
      position: absolute;
      left: 0; top: 4px;
      width: 19px; height: 19px; border-radius: 50%;
      background: var(--teal);
      box-shadow: inset 0 0 0 5px #d8f5ee;
    }
    .cta {
      margin-top: 56px;
      background: linear-gradient(135deg, #0d2c38, #00695c);
      color: #fff;
      border-radius: 34px;
      padding: 38px 44px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 40px;
    }
    .cta h2 { margin: 0 0 8px; font-size: 32px; }
    .cta p { margin: 0; color: rgba(255,255,255,.78); font-size: 20px; }
    .cta .pill { background: var(--gold); color: #15120a; padding: 16px 24px; border-radius: 999px; font-size: 18px; font-weight: 900; white-space: nowrap; }
    .note { position: absolute; bottom: 28px; left: 88px; color: rgba(255,255,255,.68); font-size: 14px; }
  </style>
</head>
<body>
  <main class="poster">
    <section class="top-band">
      <div class="brand-row">
        <div class="brand"><div class="logo-mark">HC</div><span>HC Trader</span></div>
        <div class="badge">Marketing overview | ${generatedDate}</div>
      </div>
      <div class="hero">
        <div>
          <div class="eyebrow">Trading operations, simplified</div>
          <h1>${escapeHtml(app.tagline)}</h1>
          <p>${escapeHtml(app.positioning)}</p>
          <div class="hero-stats">
            <div class="stat"><strong>24</strong><span>major modules mapped</span></div>
            <div class="stat"><strong>10</strong><span>implemented roles</span></div>
            <div class="stat"><strong>3</strong><span>confirmed platforms</span></div>
          </div>
        </div>
        <div class="phone">
          <div class="phone-screen">
            <div class="screen-title">Trader Workspace</div>
            <div class="screen-sub">Dashboard, journal, hub, accounts and tools</div>
            <div class="metric-grid">
              <div class="metric"><b>Journal</b><span>Trades, imports and attachments</span></div>
              <div class="metric"><b>Hub</b><span>Signals, analysis and learning</span></div>
              <div class="metric"><b>Prop Firm</b><span>Targets, phases and reviews</span></div>
              <div class="metric"><b>Support</b><span>Tickets, appointments and follow-up</span></div>
            </div>
            <div class="activity">
              <div>Final exam and readiness workflows keep traders moving.</div>
              <div>Recruitment tools support invite-led growth.</div>
              <div>Admins manage users, content, features and reviews.</div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <div class="tabs"><span>Home</span><span>Journal</span><span>Hub</span><span>Accounts</span></div>
    <section class="content">
      <div class="section-head">
        <h2>Main product highlights</h2>
        <p>Built as a premium mobile workspace for trading teams that need structure, visibility and scalable operations.</p>
      </div>
      <div class="highlights">${highlightCards}</div>
      <div class="lower">
        <div class="panel">
          <h2>Core modules</h2>
          ${moduleItems}
        </div>
        <div class="panel">
          <h2>Built for</h2>
          <div class="segments">${groupItems}</div>
          <h2>Business benefits</h2>
          <ul class="benefits">${benefitItems}</ul>
        </div>
      </div>
      <div class="cta">
        <div>
          <h2>A complete solution for modern trader and team management.</h2>
          <p>Designed for operational efficiency, user growth and a more organized trading experience.</p>
        </div>
        <div class="pill">HC Trader</div>
      </div>
    </section>
    <div class="note">Based on confirmed Flutter/Firebase project features. AI assistant and financial-department role require manual confirmation.</div>
  </main>
</body>
</html>`;
}

function marketingMarkdown() {
  return `# ${app.name} - App Marketing Overview

Generated date: ${generatedDate}

Project/repository: ${projectName}

## 1. Executive Summary

${app.name} is a Firebase-backed Flutter application designed to bring trading operations, trader support, education content, user management and growth workflows into one mobile platform. It gives traders a focused workspace for journals, accounts, learning, exams, tools and support, while giving administrators and operations teams a central place to manage users, content, verification, reviews, appointments, support tickets and performance activity.

The app is built for trading organizations that need more structure around onboarding, role-based operations, trader engagement and workflow visibility. Its strongest value is the way it combines multiple business needs into one connected experience: secure access, trading tools, market content, referrals, support, notifications and administration.

## 2. Product Positioning

${app.name} can be positioned as an all-in-one trading operations and trader engagement platform. It is not only a trading journal and not only an admin panel. It is a broader business platform for managing the people, content, support workflows and performance activity around a trading community or trading-focused organization.

The product addresses a practical business need: trading teams often manage education, signals, journals, verification, support, appointments, referrals and administration across separate tools. HC Trader brings these activities into a more organized mobile experience with role-aware access and bilingual support.

## 3. Key Value Propositions

- Centralizes trading workflows, learning content, account activity and administration in one app.
- Supports multiple business roles, including administrators, traders, recruiters, leaders, market analysts and signal teams.
- Improves trader visibility with journals, analytics, dashboard summaries, prop firm tracking and readiness tools.
- Helps teams scale user growth through referral codes, invite workflows and downline visibility.
- Strengthens service operations with support tickets, verification review and appointment booking.
- Supports English and Chinese users through built-in localization and dynamic translation tooling.
- Keeps users informed through notification preferences and Firebase-powered updates.

## 4. Core Features Overview

${highlights.map((item) => `### ${item.title}\n\n${item.text}\n\nWho benefits: ${benefitAudience(item.title)}\n\nKey value: ${benefitValue(item.title)}\n`).join('\n')}

## 5. Target Users and User Groups

${userGroups.map((group) => `### ${group.name}\n\nMain needs: ${group.needs}\n\nHow the app helps: ${group.help}\n`).join('\n')}

## 6. Module Highlights

${modules.map(([name, text]) => {
    const purpose = modulePurpose(name);
    return `### ${name}\n\nPurpose: ${purpose}\n\nUser benefit: ${text}\n\nBusiness benefit: ${moduleBusinessBenefit(name)}\n\nWhy it strengthens the app: ${moduleStrength(name)}\n`;
  }).join('\n')}

## 7. App Experience and User Journey

${journeys.map(([name, text]) => `### ${name}\n\n${text}\n`).join('\n')}

## 8. Competitive and Business Strengths

- All-in-one coverage: the app combines operations, education, trader tools, support and growth workflows.
- Role-aware organization: different users see workflows that match their role and purpose.
- Broad feature coverage: journals, analysis, signals, exams, support, appointments, prop firm tools and administration are all present.
- Scalable structure: referral and downline features support organized growth.
- Operational visibility: dashboards, admin reviews, audit logs and performance tracking improve transparency.
- Multilingual readiness: English and Chinese support helps the app serve a wider user base.

## 9. Multilingual and Accessibility Support

HC Trader supports English and Chinese through a custom localization layer. This is valuable for trading teams serving multilingual audiences because core labels, role names and many dynamic content areas can be shown in the user's selected language. The project also includes translation tooling for Firestore content, which helps support bilingual market content and exam material.

Manual review is still recommended for admin and publisher surfaces, where some hardcoded English text remains.

## 10. Platform Availability

- Android: Confirmed and release-focused. Android application id is configured and AAB release files are present.
- Web: Confirmed project folder and Firebase configuration are present.
- Windows: Confirmed project folder and Firebase configuration are present.
- iOS: Not confirmed. No ios/ folder was found in this project snapshot.
- macOS: Not confirmed. No macos/ folder was found in this project snapshot.
- Linux: Not confirmed. No linux/ folder was found in this project snapshot.

## 11. Marketing Summary

HC Trader is positioned as a complete mobile platform for trading teams that want to simplify operations, improve trader engagement and scale user workflows with more structure. Its strongest selling point is breadth: secure onboarding, role-based administration, journal and performance tracking, trading content, support, appointments, prop firm tools, referrals, notifications and bilingual accessibility work together in one product experience.

For stakeholders, the app presents a clear business story: better organization, better visibility, better user support and a more scalable foundation for trader and team growth.

## 12. Appendix - Confirmed Feature List

${confirmedFeatures.map((item) => `- ${item}`).join('\n')}

## 13. Items Requiring Manual Confirmation

${requiresConfirmation.map((item) => `- ${item}`).join('\n')}
`;
}

function benefitAudience(title) {
  if (title.includes('role')) return 'Admin teams, operations leads and platform owners.';
  if (title.includes('Journal')) return 'Traders, mentors and performance reviewers.';
  if (title.includes('hub')) return 'Traders, market analysts, signal providers and learning teams.';
  if (title.includes('Verification')) return 'Admins, support teams and users completing account setup.';
  if (title.includes('Referral')) return 'Leaders, recruiters and growth-focused teams.';
  if (title.includes('Bilingual')) return 'English and Chinese users, plus teams serving multilingual communities.';
  return 'Trading teams and app users.';
}

function benefitValue(title) {
  if (title.includes('role')) return 'Keeps business workflows organized by user responsibility.';
  if (title.includes('Journal')) return 'Turns daily trading activity into clearer progress tracking.';
  if (title.includes('hub')) return 'Makes trading content easier to publish, discover and consume.';
  if (title.includes('Verification')) return 'Improves trust, review control and operational follow-up.';
  if (title.includes('Referral')) return 'Supports scalable user acquisition through structured invites.';
  if (title.includes('Bilingual')) return 'Expands usability for multilingual teams and clients.';
  return 'Improves visibility and user experience.';
}

function modulePurpose(name) {
  const map = {
    'Secure onboarding': 'Create a secure first step into the platform.',
    'Admin command center': 'Give operations teams a single place to manage the app ecosystem.',
    'Trading journal': 'Help traders record activity and build a performance history.',
    'Dashboard and reports': 'Make key progress indicators easier to understand.',
    'Trading hub': 'Centralize market content, signals and learning materials.',
    'Prop firm support': 'Support funded-trader readiness and review workflows.',
    'Readiness checklist': 'Guide users through preparation steps.',
    'Support desk': 'Organize user support and staff follow-up.',
    'Appointments': 'Coordinate scheduled help or mentor sessions.',
    'Recruitment network': 'Support growth through invites and downline visibility.',
    'Profile and settings': 'Give users control over personal details, language and preferences.',
    'Notifications': 'Keep users aware of important activity and updates.',
  };
  return map[name] || 'Support a key app workflow.';
}

function moduleBusinessBenefit(name) {
  const map = {
    'Secure onboarding': 'Reduces friction while keeping account creation structured.',
    'Admin command center': 'Improves control, governance and day-to-day platform management.',
    'Trading journal': 'Creates better performance visibility for traders and reviewers.',
    'Dashboard and reports': 'Supports data-informed conversations and progress tracking.',
    'Trading hub': 'Improves content delivery and engagement.',
    'Prop firm support': 'Helps standardize funded-trader progress and review decisions.',
    'Readiness checklist': 'Encourages safer, more consistent trader preparation.',
    'Support desk': 'Improves responsiveness and accountability.',
    'Appointments': 'Makes coaching or operational sessions easier to coordinate.',
    'Recruitment network': 'Creates a scalable structure for user growth.',
    'Profile and settings': 'Improves account ownership and personalization.',
    'Notifications': 'Supports retention and timely response.',
  };
  return map[name] || 'Improves operational consistency.';
}

function moduleStrength(name) {
  const map = {
    'Secure onboarding': 'It makes the app feel secure and purposeful from the first interaction.',
    'Admin command center': 'It turns the app into a business operations platform, not only a trader tool.',
    'Trading journal': 'It anchors the trader experience around measurable behavior and progress.',
    'Dashboard and reports': 'It makes results easier to present, review and discuss.',
    'Trading hub': 'It gives the product a strong content and engagement layer.',
    'Prop firm support': 'It connects trading performance with a recognizable business progression model.',
    'Readiness checklist': 'It creates guided structure before important trading milestones.',
    'Support desk': 'It keeps user service inside the product experience.',
    'Appointments': 'It adds a human support and mentoring layer.',
    'Recruitment network': 'It helps the platform support community and business growth.',
    'Profile and settings': 'It gives users a complete account experience.',
    'Notifications': 'It helps important updates reach users at the right moment.',
  };
  return map[name] || 'It adds practical value to the platform.';
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function para(text, style = 'Normal') {
  const styleXml = style === 'Normal' ? '' : `<w:pPr><w:pStyle w:val="${style}"/></w:pPr>`;
  const lines = String(text).split('\n').map((line) => `<w:r><w:t xml:space="preserve">${escapeXml(line)}</w:t></w:r>`).join('<w:r><w:br/></w:r>');
  return `<w:p>${styleXml}${lines}</w:p>`;
}

function bullet(text) {
  return `<w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr><w:spacing w:after="80" w:line="290" w:lineRule="auto"/></w:pPr><w:r><w:t xml:space="preserve">${escapeXml(text)}</w:t></w:r></w:p>`;
}

function pageBreak() {
  return '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
}

function table(headers, rows, widths) {
  const total = widths.reduce((sum, width) => sum + width, 0);
  const grid = widths.map((width) => `<w:gridCol w:w="${width}"/>`).join('');
  const tr = (cells, header = false) => `<w:tr>${cells.map((cell, index) => `<w:tc><w:tcPr><w:tcW w:w="${widths[index]}" w:type="dxa"/>${header ? '<w:shd w:fill="F4F6F9"/>' : ''}<w:tcMar><w:top w:w="100" w:type="dxa"/><w:bottom w:w="100" w:type="dxa"/><w:start w:w="140" w:type="dxa"/><w:end w:w="140" w:type="dxa"/></w:tcMar></w:tcPr>${para(cell, header ? 'TableHeader' : 'TableText')}</w:tc>`).join('')}</w:tr>`;
  return `<w:tbl><w:tblPr><w:tblW w:w="${total}" w:type="dxa"/><w:tblInd w:w="120" w:type="dxa"/><w:tblBorders><w:top w:val="single" w:sz="4" w:color="D8DEE6"/><w:left w:val="single" w:sz="4" w:color="D8DEE6"/><w:bottom w:val="single" w:sz="4" w:color="D8DEE6"/><w:right w:val="single" w:sz="4" w:color="D8DEE6"/><w:insideH w:val="single" w:sz="4" w:color="E7EBF0"/><w:insideV w:val="single" w:sz="4" w:color="E7EBF0"/></w:tblBorders><w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid>${grid}</w:tblGrid>${tr(headers, true)}${rows.map((row) => tr(row)).join('')}</w:tbl>`;
}

function callout(title, text) {
  return `<w:tbl><w:tblPr><w:tblW w:w="9360" w:type="dxa"/><w:tblInd w:w="120" w:type="dxa"/><w:tblBorders><w:top w:val="single" w:sz="8" w:color="CFE8E2"/><w:left w:val="single" w:sz="8" w:color="CFE8E2"/><w:bottom w:val="single" w:sz="8" w:color="CFE8E2"/><w:right w:val="single" w:sz="8" w:color="CFE8E2"/></w:tblBorders></w:tblPr><w:tblGrid><w:gridCol w:w="9360"/></w:tblGrid><w:tr><w:tc><w:tcPr><w:tcW w:w="9360" w:type="dxa"/><w:shd w:fill="F4FBF8"/><w:tcMar><w:top w:w="180" w:type="dxa"/><w:bottom w:w="180" w:type="dxa"/><w:start w:w="220" w:type="dxa"/><w:end w:w="220" w:type="dxa"/></w:tcMar></w:tcPr>${para(title, 'CalloutTitle')}${para(text, 'CalloutText')}</w:tc></w:tr></w:tbl>`;
}

function docxXml() {
  const moduleRows = modules.map(([name, text]) => [name, text, moduleBusinessBenefit(name)]);
  const userRows = userGroups.map((group) => [group.name, group.needs, group.help]);
  const journeyRows = journeys.map(([name, text]) => [name, text]);

  const body = [
    para('HC Trader', 'CoverKicker'),
    para('App Marketing Overview', 'CoverTitle'),
    para(app.tagline, 'CoverSubtitle'),
    callout('Product promise', app.positioning),
    para(`Generated date: ${generatedDate}`, 'Meta'),
    para(`Project/repository: ${projectName}`, 'Meta'),
    pageBreak(),
    para('Executive Summary', 'Heading1'),
    para(`${app.name} is a Firebase-backed Flutter application designed to bring trading operations, trader support, education content, user management and growth workflows into one mobile platform. It gives traders a focused workspace for journals, accounts, learning, exams, tools and support, while giving administrators and operations teams a central place to manage users, content, verification, reviews, appointments, support tickets and performance activity.`),
    para('The strongest value is consolidation. HC Trader combines multiple business needs into one connected product experience: secure access, trading tools, market content, referrals, support, notifications and administration.'),
    para('Product Positioning', 'Heading1'),
    para('HC Trader can be positioned as an all-in-one trading operations and trader engagement platform. It is broader than a journal and more practical than a simple admin panel. It helps manage the people, content, support workflows and performance activity around a trading community or trading-focused organization.'),
    callout('Positioning line', 'A complete trading operations platform for teams, traders and growth workflows.'),
    para('Key Value Propositions', 'Heading1'),
    ...benefits.slice(0, 8).map((item) => bullet(item)),
    para('Core Features Overview', 'Heading1'),
    ...highlights.flatMap((item) => [
      para(item.title, 'Heading2'),
      para(`${item.text} ${benefitValue(item.title)}`),
    ]),
    para('Target Users and User Groups', 'Heading1'),
    table(['User group', 'Main needs', 'How HC Trader helps'], userRows, [2100, 3300, 3960]),
    para('Module Highlights', 'Heading1'),
    table(['Module', 'User value', 'Business value'], moduleRows, [2200, 3700, 3460]),
    para('App Experience and User Journey', 'Heading1'),
    table(['Journey', 'Marketing-friendly experience'], journeyRows, [2200, 7160]),
    para('Competitive and Business Strengths', 'Heading1'),
    ...[
      'All-in-one coverage across operations, education, trader tools, support and growth workflows.',
      'Role-aware organization that lets different user groups focus on the work that matters to them.',
      'Broad feature coverage with journals, analysis, signals, exams, support, appointments, prop firm tools and administration.',
      'Scalable structure for referral-led growth and downline visibility.',
      'Operational visibility through dashboards, admin reviews, audit logs and performance tracking.',
      'Multilingual readiness for English and Chinese audiences.',
    ].map((item) => bullet(item)),
    para('Multilingual and Accessibility Support', 'Heading1'),
    para('HC Trader supports English and Chinese through a custom localization layer. This is a meaningful business advantage for teams serving multilingual audiences, because core labels, role names and many dynamic content areas can appear in the user selected language. The project also includes translation tooling for Firestore content, which supports bilingual market content and exam material.'),
    para('Manual review is still recommended for admin and publisher surfaces, where some hardcoded English text remains.'),
    para('Platform Availability', 'Heading1'),
    table(['Platform', 'Marketing status'], [
      ['Android', 'Confirmed and release-focused, with configured package and AAB release files.'],
      ['Web', 'Confirmed project folder and Firebase configuration are present.'],
      ['Windows', 'Confirmed project folder and Firebase configuration are present.'],
      ['iOS', 'Not confirmed. No ios/ folder found.'],
      ['macOS', 'Not confirmed. No macos/ folder found.'],
      ['Linux', 'Not confirmed. No linux/ folder found.'],
    ], [2200, 7160]),
    para('Marketing Summary', 'Heading1'),
    para('HC Trader is valuable because it gives trading teams a more organized way to operate, support users, publish content and guide trader progress. It presents a clear business story: better organization, better visibility, stronger support and a scalable foundation for trader and team growth.'),
    para('Appendix - Confirmed Feature List', 'Heading1'),
    ...confirmedFeatures.map((item) => bullet(item)),
    para('Items Requiring Manual Confirmation', 'Heading1'),
    ...requiresConfirmation.map((item) => bullet(item)),
    '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>',
  ].join('');

  return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>${body}</w:body></w:document>`;
}

function stylesXml() {
  return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:after="160" w:line="320" w:lineRule="auto"/><w:jc w:val="both"/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="22"/><w:color w:val="1F2933"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="CoverKicker"><w:name w:val="Cover Kicker"/><w:pPr><w:spacing w:before="900" w:after="180"/><w:jc w:val="center"/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:caps/><w:color w:val="D99A17"/><w:sz w:val="24"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="CoverTitle"><w:name w:val="Cover Title"/><w:pPr><w:spacing w:after="120"/><w:jc w:val="center"/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="09212F"/><w:sz w:val="56"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="CoverSubtitle"><w:name w:val="Cover Subtitle"/><w:pPr><w:spacing w:after="420"/><w:jc w:val="center"/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:color w:val="3D5561"/><w:sz w:val="28"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Meta"><w:name w:val="Meta"/><w:pPr><w:spacing w:after="80"/><w:jc w:val="center"/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:color w:val="5A6B73"/><w:sz w:val="20"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:before="360" w:after="200"/><w:keepNext/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="2E74B5"/><w:sz w:val="32"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:spacing w:before="240" w:after="120"/><w:keepNext/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="2E74B5"/><w:sz w:val="26"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="TableHeader"><w:name w:val="Table Header"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="40" w:line="280" w:lineRule="auto"/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="0B2545"/><w:sz w:val="19"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="TableText"><w:name w:val="Table Text"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="40" w:line="280" w:lineRule="auto"/><w:jc w:val="left"/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:color w:val="1F2933"/><w:sz w:val="18"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="CalloutTitle"><w:name w:val="Callout Title"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="60"/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="00796B"/><w:sz w:val="22"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="CalloutText"><w:name w:val="Callout Text"/><w:basedOn w:val="Normal"/><w:pPr><w:spacing w:after="0" w:line="300" w:lineRule="auto"/><w:jc w:val="left"/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:color w:val="263B45"/><w:sz w:val="21"/></w:rPr></w:style>
</w:styles>`;
}

function numberingXml() {
  return `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="0">
    <w:multiLevelType w:val="hybridMultilevel"/>
    <w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/><w:lvlJc w:val="left"/><w:pPr><w:tabs><w:tab w:val="num" w:pos="540"/></w:tabs><w:ind w:left="540" w:hanging="280"/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:hint="default"/></w:rPr></w:lvl>
  </w:abstractNum>
  <w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>
</w:numbering>`;
}

function escapeXml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;');
}

function docxParts() {
  const contentTypes = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>`;
  const rels = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>`;
  const documentRels = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rIdNumbering" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/></Relationships>`;
  const core = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>HC Trader App Marketing Overview</dc:title><dc:creator>Codex</dc:creator><cp:lastModifiedBy>Codex</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">${generatedDate}T00:00:00Z</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">${generatedDate}T00:00:00Z</dcterms:modified></cp:coreProperties>`;
  const appProps = `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Codex OpenXML Generator</Application><Company>HC Gaming</Company><AppVersion>1.0</AppVersion></Properties>`;
  return [
    ['[Content_Types].xml', contentTypes],
    ['_rels/.rels', rels],
    ['word/document.xml', docxXml()],
    ['word/styles.xml', stylesXml()],
    ['word/numbering.xml', numberingXml()],
    ['word/_rels/document.xml.rels', documentRels],
    ['docProps/core.xml', core],
    ['docProps/app.xml', appProps],
  ];
}

const crcTable = (() => {
  const table = new Uint32Array(256);
  for (let i = 0; i < 256; i += 1) {
    let c = i;
    for (let k = 0; k < 8; k += 1) {
      c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
    }
    table[i] = c >>> 0;
  }
  return table;
})();

function crc32(buffer) {
  let crc = 0xFFFFFFFF;
  for (const byte of buffer) crc = crcTable[(crc ^ byte) & 0xFF] ^ (crc >>> 8);
  return (crc ^ 0xFFFFFFFF) >>> 0;
}

function dosTimeDate() {
  const date = new Date(`${generatedDate}T00:00:00Z`);
  return {
    dosTime: (date.getUTCHours() << 11) | (date.getUTCMinutes() << 5) | Math.floor(date.getUTCSeconds() / 2),
    dosDate: ((date.getUTCFullYear() - 1980) << 9) | ((date.getUTCMonth() + 1) << 5) | date.getUTCDate(),
  };
}

function writeZip(filePath, entries) {
  const { dosTime, dosDate } = dosTimeDate();
  const localParts = [];
  const centralParts = [];
  let offset = 0;
  for (const [name, content] of entries) {
    const nameBuffer = Buffer.from(name, 'utf8');
    const data = Buffer.from(content, 'utf8');
    const compressed = zlib.deflateRawSync(data);
    const crc = crc32(data);
    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(20, 4);
    local.writeUInt16LE(0x0800, 6);
    local.writeUInt16LE(8, 8);
    local.writeUInt16LE(dosTime, 10);
    local.writeUInt16LE(dosDate, 12);
    local.writeUInt32LE(crc, 14);
    local.writeUInt32LE(compressed.length, 18);
    local.writeUInt32LE(data.length, 22);
    local.writeUInt16LE(nameBuffer.length, 26);
    local.writeUInt16LE(0, 28);
    localParts.push(local, nameBuffer, compressed);
    const central = Buffer.alloc(46);
    central.writeUInt32LE(0x02014b50, 0);
    central.writeUInt16LE(20, 4);
    central.writeUInt16LE(20, 6);
    central.writeUInt16LE(0x0800, 8);
    central.writeUInt16LE(8, 10);
    central.writeUInt16LE(dosTime, 12);
    central.writeUInt16LE(dosDate, 14);
    central.writeUInt32LE(crc, 16);
    central.writeUInt32LE(compressed.length, 20);
    central.writeUInt32LE(data.length, 24);
    central.writeUInt16LE(nameBuffer.length, 28);
    central.writeUInt16LE(0, 30);
    central.writeUInt16LE(0, 32);
    central.writeUInt16LE(0, 34);
    central.writeUInt16LE(0, 36);
    central.writeUInt32LE(0, 38);
    central.writeUInt32LE(offset, 42);
    centralParts.push(central, nameBuffer);
    offset += local.length + nameBuffer.length + compressed.length;
  }
  const centralOffset = offset;
  const centralBuffer = Buffer.concat(centralParts);
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0);
  end.writeUInt16LE(0, 4);
  end.writeUInt16LE(0, 6);
  end.writeUInt16LE(entries.length, 8);
  end.writeUInt16LE(entries.length, 10);
  end.writeUInt32LE(centralBuffer.length, 12);
  end.writeUInt32LE(centralOffset, 16);
  end.writeUInt16LE(0, 20);
  fs.writeFileSync(filePath, Buffer.concat([...localParts, centralBuffer, end]));
}

function writeOutputs() {
  fs.mkdirSync(outDir, { recursive: true });
  fs.writeFileSync(path.join(outDir, 'app_marketing_poster.html'), posterHtml(), 'utf8');
  fs.writeFileSync(path.join(outDir, 'App_Marketing_Overview.md'), marketingMarkdown(), 'utf8');
  writeZip(path.join(outDir, 'App_Marketing_Overview.docx'), docxParts());
  const summary = {
    generatedDate,
    appName: app.name,
    targetUsers: userGroups.map((group) => group.name),
    featureGroups: highlights.map((item) => item.title),
    confirmedFeatures: confirmedFeatures.length,
    requiresConfirmation: requiresConfirmation.length,
    files: [
      'app_marketing_poster.html',
      'App_Marketing_Overview.md',
      'App_Marketing_Overview.docx',
    ],
  };
  fs.writeFileSync(path.join(outDir, 'marketing_generation_summary.json'), `${JSON.stringify(summary, null, 2)}\n`, 'utf8');
  console.log(JSON.stringify(summary, null, 2));
}

writeOutputs();
