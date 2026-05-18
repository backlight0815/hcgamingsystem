<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommunityShowcasePage extends Model
{
    use HasFactory;

    public const DEFAULT_SLUG = 'hc-trading-community';

    protected $fillable = [
        'slug',
        'hero_kicker',
        'hero_title',
        'hero_subtitle',
        'hero_intro',
        'poster_image',
        'primary_cta_label',
        'primary_cta_url',
        'secondary_cta_label',
        'secondary_cta_url',
        'entry_requirements',
        'core_services',
        'secondary_services',
        'service_principle',
        'risk_disclaimer',
        'is_published',
    ];

    protected $casts = [
        'entry_requirements' => 'array',
        'core_services' => 'array',
        'secondary_services' => 'array',
        'is_published' => 'boolean',
    ];

    public static function defaultContent(): array
    {
        return [
            'slug' => self::DEFAULT_SLUG,
            'hero_kicker' => 'HC COMMUNITY REAL SUPPORT · LONG-TERM GROWTH',
            'hero_title' => 'HC COMMUNITY 交易学习社群',
            'hero_subtitle' => '金融市场学习｜实战陪跑｜长期成长',
            'hero_intro' => '真实支持，不卖梦想；用清晰的方法与稳定的陪伴，帮助认真学习的交易者走得更久。',
            'poster_image' => 'upload/community_showcase/hc-trading-community-poster-cn.png',
            'primary_cta_label' => '咨询加入',
            'primary_cta_url' => '/contact',
            'secondary_cta_label' => '注册账户',
            'secondary_cta_url' => '/register',
            'entry_requirements' => [
                [
                    'label' => '一次性社群费',
                    'value' => 'RM899',
                    'description' => '用于筛选真正认真、愿意长期学习交易的成员；同时投入社群运营、资源建设与服务升级。',
                ],
                [
                    'label' => '交易本金',
                    'value' => 'USD 500',
                    'description' => '作为参与金融市场交易练习与实盘操作的资金基础，需自行做好风险评估与资金管理。',
                ],
            ],
            'core_services' => [
                ['title' => '一对一咨询与课程', 'description' => '针对成员学习阶段提供更贴近个人情况的指导。'],
                ['title' => '金融市场指导与及时支持', 'description' => '在学习和实战过程中提供稳定陪伴与问题处理。'],
                ['title' => '每日市场分析报告，含指标提醒', 'description' => '帮助成员持续建立市场观察与交易准备习惯。'],
                ['title' => '交易信号与逻辑说明', 'description' => '不仅提供参考方向，也解释背后的判断逻辑。'],
                ['title' => 'EA 自动化工具服务', 'description' => '提供自动化工具相关服务与使用支持。'],
                ['title' => '课程录影回放', 'description' => '方便成员复习重点内容，巩固长期学习节奏。'],
            ],
            'secondary_services' => [
                ['title' => '公开直播分析与 Q&A', 'description' => '通过直播交流增强市场理解与问题反馈。'],
                ['title' => '跟单服务', 'description' => '提供额外的交易辅助选择。'],
                ['title' => '不定期 Prop Firm 抽奖', 'description' => '根据社群安排提供阶段性活动机会。'],
                ['title' => '合作伙伴不定期加课', 'description' => '引入合作资源，丰富成员学习内容。'],
            ],
            'service_principle' => '我们只提供真实、可执行、能力范围内的支持；不夸大收益、不售卖梦想、不承诺不现实结果。',
            'risk_disclaimer' => '投资交易涉及风险，过往表现不代表未来结果，请理性评估并做好资金管理。',
            'is_published' => true,
        ];
    }
}
