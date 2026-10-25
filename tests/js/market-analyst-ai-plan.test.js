const assert = require('node:assert/strict');
const { parsePlan, serializePlan, mergePlan } = require('../../public/backend/assets/js/market-analyst-ai-plan.js');

const legacy = [
    '交易执行计划',
    '',
    '方向 Bias：手写偏多观点',
    '主要结构：手写结构',
    '失效条件：',
    '',
    '计划 A - 顺势延续',
    '进场：手写进场',
    '止损：',
    '止盈：',
    '理由：',
    '',
    '计划 B - 回调进场',
    '进场：',
    '止损：',
    '止盈：',
    '理由：',
    '',
    '计划 C - 拒绝进场 / 不交易',
    '条件：',
    '处理方式：',
    '',
    '风险管理',
    '单笔最大风险：',
    '必须确认信号：',
    '交易时段备注：'
].join('\n');

const original = parsePlan(legacy);
assert.equal(original.bias.summary, '手写偏多观点');
assert.equal(original.planA.entry, '手写进场');

const generated = {
    bias: { summary: 'AI 观点', primaryStructure: 'AI 结构', invalidationCondition: 'AI 失效' },
    planA: { entry: 'AI 进场', stopLoss: 'AI 止损', takeProfit: 'AI 止盈', reason: 'AI 理由' },
    planB: { entry: 'AI 回调', stopLoss: 'AI 止损', takeProfit: 'AI 止盈', reason: 'AI 理由' }
};

const filled = mergePlan(original, generated, 'empty', null);
assert.equal(filled.bias.summary, '手写偏多观点');
assert.equal(filled.planA.entry, '手写进场');
assert.equal(filled.planA.stopLoss, 'AI 止损');

const section = mergePlan(original, generated, 'section', 'planB');
assert.equal(section.bias.summary, '手写偏多观点');
assert.equal(section.planA.entry, '手写进场');
assert.equal(section.planB.entry, 'AI 回调');
assert.equal(parsePlan(serializePlan(section)).planB.entry, 'AI 回调');

assert.equal(parsePlan('交易执行计划\n原有自由文字内容'), null);
assert.equal(parsePlan('交易执行计划\n计划 A - 顺势延续\n进场：第一行\n第二行'), null);

console.log('Market Analyst AI plan browser-state tests passed.');
