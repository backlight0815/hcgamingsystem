-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 06, 2024 at 12:32 PM
-- Server version: 5.7.43-log-cll-lve
-- PHP Version: 8.1.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hcgaming`
--

-- --------------------------------------------------------

--
-- Table structure for table `abouts`
--

CREATE TABLE `abouts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_description` text COLLATE utf8mb4_unicode_ci,
  `long_description` text COLLATE utf8mb4_unicode_ci,
  `about_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `abouts`
--

INSERT INTO `abouts` (`id`, `title`, `short_title`, `short_description`, `long_description`, `about_image`, `created_at`, `updated_at`) VALUES
(1, 'About Me', 'Explore More About Myself', 'I find solace in solitude, engaging in activities I enjoy, while striving to achieve my personal life goals and dreams. Although I am not a professional website developer, I consistently challenge myself to improve by 1% and embrace every challenge that comes my way. While my circle of friends is small, I am grateful for the presence of genuine companions. My goals are practical and straightforward. Ideally, I aim to join a company that offers flexible working hours and locations, allowing me the freedom to work from home for at least one or two days. I believe in avoiding unnecessary overtime and look forward to a company that continuously improves its systems. After work, I aspire to earn the income I desire and engage in activities that align with my personal interests.', 'Hello, my name is Sua Kai Young, and I am currently pursuing a Bachelor\'s degree in Information Technology with a specialization in Mobile Technology. I have gained some valuable working experiences during my studies. I worked as a Website Development Assistant for 3 months, where I honed my skills in using Wordpress. Additionally, I had the opportunity to work as an intern Software Tester for 3 and a half months, further expanding my knowledge in this area.<br /><br />In my free time, I like to engage in part-time sales activities to earn some extra income. However, my true passion lies in continuous learning and self-improvement, particularly in programming languages. I make it a point to enhance my skills by at least 1-2% every day. Furthermore, I enjoy playing badminton with friends, even though I consider myself a beginner in the sport.<br /><br />While I have many strengths, such as being hard-working and diligent in completing tasks and learning processes, I also recognize some areas for improvement. I sometimes struggle with communication and language skills, which can hinder effective interaction with others. Additionally, I occasionally lack confidence and self-motivation, especially during challenging times. Furthermore, I find it difficult to handle the pressures and stress that come with work.<br /><br />Looking ahead, my career goal is to become an entry-level or junior Laravel Programmer. I prioritize achieving a work-life balance and believe in pursuing other interests outside of work. Mental health is also important to me, and I strive to maintain a healthy and balanced lifestyle.<br /><br />In terms of my skills, I have a basic understanding of Android development using Kotlin and iOS development using Swift. However, my expertise lies in website development, where I am proficient in languages such as PHP, C#, HTML, CSS, and JavaScript. I have also acquired a moderate level of knowledge in the Laravel framework through self-learning. I have experience developing various functions for e-commerce websites, such as recruitment, e-wallet, and account management. Moreover, I possess the ability to perform thorough testing on websites to identify potential issues related to UI/UX and functionality, drawing from my knowledge of Human-Computer Interaction. Furthermore, I have explored the field of Data Analysis using R languages, expanding my skill set even further.<br /><br />To conclude, I am 23 years old, and my birthday is on August 15, 2000.', 'upload/home_about/1774081006700484.jpg', NULL, '2023-08-12 18:58:25');

-- --------------------------------------------------------

--
-- Table structure for table `acknowledgement`
--

CREATE TABLE `acknowledgement` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `long_description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zipcode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`id`, `user_id`, `name`, `street`, `street_2`, `zipcode`, `city`, `state`, `phone_no`, `created_at`, `updated_at`) VALUES
(1, 1, 'Sua Kai Young', '24 Jalan Permas 5/20', 'Bandar Baru Permas Jaya', '81750', 'Malaysia', 'Kuala Lumpur', NULL, '2023-08-18 22:17:02', '2023-08-18 22:17:02'),
(2, 15, 'Kai Young', '24 Jalan Permas 5/20', NULL, '81750', 'Johor Bahru', 'Johor Bahru', NULL, '2023-08-18 22:21:28', '2023-08-18 23:33:51'),
(3, 8, 'Sua Kai Young', '24 Jalan Permas 5/20', 'Bandar Baru Permas Jaya', '81750', 'ccdcdcd', 'cdcdcd', NULL, '2023-08-20 05:42:03', '2023-08-27 01:31:03');

-- --------------------------------------------------------

--
-- Table structure for table `blogs`
--

CREATE TABLE `blogs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `blog_category_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blog_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blog_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blog_tags` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blog_description` text COLLATE utf8mb4_unicode_ci,
  `page_views` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blogs`
--

INSERT INTO `blogs` (`id`, `blog_category_id`, `blog_title`, `blog_image`, `blog_tags`, `blog_description`, `page_views`, `created_at`, `updated_at`) VALUES
(3, '3', 'HC Gaming Website Development Journey', 'upload/blog/1774574649952476.jpg', 'Journey', '<p>The process of web development is both fascinating and serendipitous. It all began on March 17, 2023, when I received an offer letter from an IT company for an internship position. The job description and title clearly indicated \"Intern of Laravel Programmer.\" In order to facilitate an easy transition on my first day and become familiar with the role, I began self-learning the basics of the Laravel framework in April, dedicating an entire month to the process. However, when I started working on my first day, I realized that I had been hired as a software tester rather than a Laravel Programmer. Nevertheless, since I had already acquired these foundational skills, I thought, why not continue to persevere.</p>\r\n<p>I decided to continue my self-learning journey, drawing inspiration from the shortcomings of my previous company and seeking to challenge myself with a significant project. I resolved to develop a website for an e-commerce agency system and simulate the entire website development cycle, including planning, development, testing, and deployment.</p>\r\n<p>Familiarizing myself with the Laravel framework and developing functions I had never encountered before was a complex and time-consuming task for a beginner. It involved learning and understanding coding, gradually developing functions until they worked, and then thoroughly testing them. If issues arose, I tackled them one by one. This became a repetitive process of development, testing, and troubleshooting. It wasn\'t just about making things work; I also had to consider the user flow and anticipate potential issues that might arise from different ways of using the functions.</p>\r\n<p>During my internship, I dedicated 9-10 hours to work, and after work, I spent 6-8 hours daily developing my website system. There were only about 14 days when I took breaks or didn\'t dedicate a full day to development. Most days were consumed by my passion for creating my system. Consequently, the pressure on me kept building, leading to moments of feeling overwhelmed. It was only after meeting someone that I began to learn how to balance my lifestyle and prevent myself from becoming emotionally distressed.</p>\r\n<p>Eventually, my website reached a milestone as I completed the first phase and deployed the live version. However, deploying the live version doesn\'t imply that my system is perfected and the project is closed. It merely indicates the progress of the first phase and signals that any new functions or improvements will be directly updated in the live version. Everyone is welcome to review my website and provide valuable feedback and suggestions. This website isn\'t solely about showcasing my coding, problem-solving, testing, or other skills; it\'s also about paving the way for my future, helping me find my ideal job and startup website system.</p>', 7, '2023-08-18 05:44:26', '2024-03-17 11:09:25'),
(4, '4', '低质量的社交不如高质量的独处', 'upload/blog/1776479810112236.jpg', '社交,独处', '<p>不知道你有没有过这样的感触：<strong>一群人狂欢时的<a title=\"孤独\" href=\"http://www.520740.com/zt/gudu\" target=\"_blank\" rel=\"noopener\">孤独</a>，有时会胜过一个人<a title=\"独处\" href=\"http://www.520740.com/zt/duchu\" target=\"_blank\" rel=\"noopener\">独处</a>。与其浪费<a title=\"时间\" href=\"http://www.520740.com/zt/shijian\" target=\"_blank\" rel=\"noopener\">时间</a>精力，去做一些无用的社交，倒不如学会如何与自己相处。</strong></p>\r\n<p>　　独处，当然是一个人。你要耐得住<a title=\"寂寞\" href=\"http://www.520740.com/zt/jimo\" target=\"_blank\" rel=\"noopener\">寂寞</a>，专注自己正在做的事。</p>\r\n<p>　　如果是在家里，首先要把房间打扫干净。看着窗明几净、井井有条的家，<a title=\"心情\" href=\"http://www.520740.com/zt/xinqing\" target=\"_blank\" rel=\"noopener\">心情</a>自然就不会太差。</p>\r\n<p>　　一个人在家，也要好好打扮自己。可能不需要浓妆艳抹，或者西装革履，但是一定要舒适整洁。<strong>与其说是一种相处，可能更像是对自己内心的一次修炼。</strong></p>\r\n<p>　　可以放几首舒缓的音乐，渴了就泡一壶茶，或者一杯咖啡。可以抱着一本<a title=\"喜欢\" href=\"http://www.520740.com/zt/xihuan\" target=\"_blank\" rel=\"noopener\">喜欢</a>的书去读，也可以放松心情，放空自己。</p>\r\n<p>　　当然，一个人的时候除了呆在家里，你还可以出去做些喜欢的事。</p>\r\n<p>　　比如去看一场电影，或者去看看画展、听听音乐会，可能会给你意外的惊喜。</p>\r\n<p>　　健康的身体，才是<a title=\"生活\" href=\"http://www.520740.com/zt/shenghuo\" target=\"_blank\" rel=\"noopener\">生活</a>中一切的基石。<strong>一个人独处的时候，更要养成一个健康的作息<a title=\"习惯\" href=\"http://www.520740.com/zt/xiguan\" target=\"_blank\" rel=\"noopener\">习惯</a>。</strong>你可以去健身房锻炼，当然也可以到楼下跑步。</p>\r\n<p>　　如果你喜欢户外活动，那一个人的生活可能会更加多姿多彩。骑单车、爬山、游泳，<a title=\"旅行\" href=\"http://www.520740.com/zt/lvxing\" target=\"_blank\" rel=\"noopener\">旅行</a>，总有一款能让你身心愉悦。</p>\r\n<p>　　<strong>专注自己正在做的事，时刻保持<a title=\"阳光\" href=\"http://www.520740.com/zt/yangguang\" target=\"_blank\" rel=\"noopener\">阳光</a><a title=\"心态\" href=\"http://www.520740.com/zt/xintai\" target=\"_blank\" rel=\"noopener\">心态</a>，开心去做你的事。</strong>你也可以把独处当作是一条靠近<a title=\"梦想\" href=\"http://www.520740.com/zt/mengxiang\" target=\"_blank\" rel=\"noopener\">梦想</a>的必经之路。</p>\r\n<p>　　如果你喜欢<a title=\"文学\" href=\"http://www.520740.com/\" target=\"_blank\" rel=\"noopener\">文学</a>，那就静静的<a title=\"读书\" href=\"http://www.520740.com/zt/dushu\" target=\"_blank\" rel=\"noopener\">读书</a>。学习新东西，也不仅仅是看书。比如重新学一门外语，或者绘画、乐器等，参加培训班可能会很有意思，当然从网上也可以学习很多东西。</p>\r\n<p>　　试着养一只宠物，可以是一只猫，或者一条狗。很多人都说，在养了宠物之后一个人的<a title=\"幸福\" href=\"http://www.520740.com/zt/xingfu\" target=\"_blank\" rel=\"noopener\">幸福</a>感爆棚。可能因为无聊的时候有了陪伴，也可能心里有了<a title=\"牵挂\" href=\"http://www.520740.com/zt/qiangua\" target=\"_blank\" rel=\"noopener\">牵挂</a>。</p>\r\n<p>　　当然，一切皆有度，<strong>不盲目社交，也不孤独终老，保持一颗乐观的心态。</strong>这样无论你是群居还是独处，都可以过得幸福。</p>', 3, '2023-09-08 14:26:08', '2024-03-11 06:26:28'),
(5, '5', '一个Bars的故事', 'upload/blog/1779984717367093.jpg', '感情', '<p>曾经，我一直在大学生活中虚度时光，不加目标地过着每一天。然而如今，我却意外地结识了一位知心知意的朋友，也对喜欢的某人以及相识的经历留下了深深的回忆。那张名为《乱拍》的照片仿佛给了过去回忆一个完美的句号。然而，缘分却使我有机会不断创造新的回忆。尽管过去的5个月并没有太多惊心动魄的经历，但每一次的相聚都成为了珍贵的时刻。在这短暂的时光里，我领悟到了许多人生道理，也学到了许多大学生涯中无法领悟的智慧。这些经历不仅点燃了我内心的绝望，也赋予了我对人生的新希望。<br /><br /></p>\r\n<p>我希望能在未来的人生中继续保持这样深厚的友情关系，也愿意结识更多优秀的朋友。我渴望能加入一家理想的公司，实现自己的小目标，得到自己渴望的东西，包括心仪的那个人。尽管相识至今仅有5个月，说长不长，说短不短，但我意识到事物的变迁是如此巨大。然而，只要我们身心健康，安稳顺利，快乐幸福，这已足够。</p>', 10, '2023-10-14 06:55:08', '2024-03-20 01:04:18'),
(6, '1', '让我最放不下的人', 'upload/blog/1792760559801159.jpg', 'Love', '你有试过吗，<br />为了一个喜欢的人，<br />而忘掉了时间，甚至忘记自己的难过。<br /><br />明明你已经很累很累，<br />你却为了让他可以重拾笑脸，<br />付出了无数心血与时间。<br />明明你自己也早已伤痕累累，<br />有多少次，你却变成一个最乐观最自信的人，<br />去听对方的烦恼，为她加油打气，<br />陪她静静度过无数难过的深夜，<br />就只望对方可以从伤痛中复原过来，<br />就只望对方能够重新想起，<br />自己也是值得拥有快乐的权力，<br />也是值得去被爱、被好好珍惜。<br /><br />即使其实，<br />你自己也很久没有再去微笑了........<br />你已经走了很远很远的路，<br />这些日子以来，遇过太多不对的人与事，<br />而明天依然还有很多看不清的未来。<br />你累了，真的，<br />曾经你都以为，余生再没有什么事情，<br />值得去期待去相信去坚持........<br />但如今你却好想好像，<br />去守护眼前的这一个人，<br />不再有半点犹豫，不要留任何遗憾。<br />就算心里偶尔会有点不安，<br />但你却愿意为她展现最温柔的笑容，<br />就算前路再艰难，世界再混乱黑暗，<br />但你都不会不离不弃，伴他撑到最后。<br />就只望在对方需要自己的时候，<br />可以成为一个坚实安全的依靠，<br />替她遮风挡雨，消烦解忧。<br /><br />为了他，你可以忘掉自己的难过，<br />即使你本来的性格并不坚强，<br />但如今你却变成一个更温柔更成熟的人，<br />是因为你真的太喜欢这一个人吗，<br />还是你终于寻回，重新区爱一个人的勇气......<br /><br />即使最后她可能未必会明白，<br />你一直所承受的上吧与疲累，<br />但你说，只要她最后可以幸福快乐，<br />那就已经很足够了....<br /><br />只要可以继续伴在他的身边，<br />一直相守到老，就好。', 22, '2023-12-27 02:30:29', '2024-03-31 02:12:00'),
(7, '6', 'Making a Difference: Stories of Impactful Donations', 'upload/blog/1786688107197893.jpg', 'Donation', '<p>Have you ever found yourself hesitant to contribute to charitable causes? I certainly had, until a friend posed a meaningful question: \"Would you like to perform acts of kindness and accumulate merits?\" This question marked the beginning of a transformative journey into the world of philanthropy.</p>\r\n<p>Prior to this, I had never made donations to charitable organizations. However, inspired by my friend\'s suggestion, I started contributing based on my personal capacity. While I may not address each case individually, and the amounts might not be significant, I\'ve discovered that each donation brings me joy and, more importantly, provides assistance to those in need.</p>\r\n<p>I\'ve come to realize that even small donations, when aggregated, can make a significant impact. It\'s not about the size of the contribution but the cumulative effect, epitomizing the proverb \"积少成多\" (accumulate small amounts to make a large sum). I\'ve witnessed cases where these collective efforts have raised enough funds to cover medical expenses, enabling individuals to receive treatment and embark on a journey of recovery.</p>\r\n<p>One particular case that stands out involves individuals who require a modest amount, say RM10, for medical treatment. While it might seem like a small sum, for those in need, it can be a crucial lifeline. By extending a helping hand, even one person can make a substantial impact, potentially altering the course of someone\'s life.</p>\r\n<p>These individuals aren\'t my relatives or friends, nor are they part of my immediate family. However, I believe in maintaining a compassionate heart and helping to the best of our abilities. Many people\'s lives can be positively affected with just a small contribution, and I\'ve personally experienced the joy that comes from knowing I\'ve played a part in making a difference.</p>\r\n<p>Therefore, I made a promise to myself that, whenever my capabilities allow, I will offer assistance. I\'m not here to compel everyone to donate alongside me; rather, I\'m sharing my personal perspective and experiences. I encourage everyone to, within their means, extend help to those who genuinely need it. Their lives are already challenging, and they shouldn\'t have to endure additional burdens from serious health crises.</p>', 9, '2023-12-30 06:42:39', '2024-03-10 17:56:29'),
(8, '1', '重逢之夜：五个月后的心灵交流', 'upload/blog/1792777237110069.jpg', '爱情,感情,感想', '<p>在2月23日的晚上，我刚结束了一天的课程，在学校食堂吃晚餐时，发生了一件意外的事情。那位女孩突然约我出来见面，让我感到非常愉快。虽然一开始我们之间有几分钟的沉默，但我很快开口关心她的近况，然后我们慢慢地恢复了以前的感觉。毕竟，我们已经有5个月没有单独约会见面了。</p>\r\n<p>我们相约到一个熟悉的地方一起喝酒，聊天，倾诉彼此的心事，分享生活中的小秘密。我们玩起了一个有趣的游戏，输的人要玩真心话游戏并喝一口酒，赢的一方则负责问问题。通过这个游戏，我内心的一些困扰也得到了释放，同时我也开始喜欢上了赵雷的歌曲《我记得》。我们还约定如果有机会，她会参加我人生中最重要的毕业典礼。</p>\r\n<p>尽管我们有很长一段时间没有真正聊天，甚至已经觉得彼此已经成了熟悉的陌生人，但这次重逢让我对很多事情有了新的体会。我收集了很多网友和朋友的意见，但最终我还是选择相信那位女孩是真诚而真心的。</p>\r\n<p>尽管我们现在很少在线上聊天，因为我已经习惯不依赖线上交流了，但我的信念和心情却没有改变。我对她的思念也在加深，她成为我进步的动力，激励我尝试新事物。</p>\r\n<p>在那晚的酒吧，我摸了她的头而她没有任何抗拒，这是我们之间的一个小秘密。而另一个小秘密是，她知道我曾经赌博赌光了钱。此外，酒吧的服务员似乎也对我印象深刻，他可能已经注意到了我对那位女孩的特殊情感。</p>\r\n<p>最近，我经常听一首歌曲，因为它能够很好地描述我的心境，那就是趙雷的《我記得》。</p>\r\n<p>通过这次重逢，我体会到了很多，尽管心中仍有疑惑，但我愿意相信这份真诚的情感，继续前行</p>', 5, '2024-03-06 11:46:46', '2024-03-24 03:29:04'),
(9, '5', '爱而不得篇', 'upload/blog/1794954821109975.jpg', '爱情,感情', '明知道我与她并没有结果，<br />明知道她对我并没有意思，<br />明知道她冷漠是想让我放弃，<br />明知道她心中有人，也有对其他人有好感，<br />明知道她喜欢的类型，我短期内无法成为她想要的类型。<br />明知道我们见面的机会并不多。<br /><br />可是我却这么的深爱她，无论我怎样的忙碌都会日思夜想着她。<br /><br />我无数次的幻想都会想到我们之前的回忆是挺好的，虽然在她眼里是不值一提。<br /><br />每周2-3次，她都会频繁出现在我的梦境里甚至更多。噩梦，好梦，什么类型的梦境都有。<br /><br />当我一次次自欺欺人的时候，我都会告诉我自己 当朋友也是挺好的。当作一个推动力也是很好一个办法。<br /><br />可她知不知道，我现在的所改变，以及生活和规划。都紧紧关联着她。<br /><br />我甚至觉得拥有她，是我的全世界。失去了她，就仿佛我失去了全世界的一个心情。<br /><br />我每一次进入很崩溃的状态，每一次都只想要与你分享我的困扰。<br /><br />我每一次遇到有趣的事情，每次都只会先想到与你分享。<br /><br />可是我的热情一次又一次的被消耗，直到热情的火慢慢的熄灭。<br /><br />可热情的火熄灭了，我何时才能把爱才能慢慢熄灭。过我自己的生活，过各自想要的生活。并且祝福她能过上好的生活，遇到合适的男朋友。', 1, '2024-03-30 12:38:32', '2024-03-31 02:12:06');

-- --------------------------------------------------------

--
-- Table structure for table `blog_categories`
--

CREATE TABLE `blog_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `blog_category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blog_categories`
--

INSERT INTO `blog_categories` (`id`, `blog_category`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '感情', NULL, '2023-12-27 02:09:34', NULL),
(2, '帅哥', NULL, '2023-08-18 02:48:45', '2023-08-18 02:48:45'),
(3, 'Website Development', NULL, '2023-08-18 05:02:19', NULL),
(4, '社交', NULL, NULL, NULL),
(5, '感想', NULL, NULL, NULL),
(6, 'Charity and Giving', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `guest_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`id`, `user_id`, `product_id`, `quantity`, `created_at`, `updated_at`, `guest_id`) VALUES
(23, NULL, 18, 1, '2023-08-17 04:41:43', '2023-08-17 04:41:43', '1'),
(24, NULL, 17, 1, '2023-08-17 04:50:00', '2023-08-17 04:50:00', '1'),
(25, NULL, 18, 1, '2023-08-17 04:50:06', '2023-08-17 04:50:06', '1'),
(26, NULL, 17, 1, '2023-08-17 04:50:49', '2023-08-17 04:50:49', '1'),
(28, NULL, 17, 1, '2023-08-17 04:51:55', '2023-08-17 04:51:55', '1'),
(30, NULL, 18, 1, '2023-08-17 04:53:18', '2023-08-17 04:53:18', '1'),
(31, NULL, 20, 1, '2023-08-17 05:45:25', '2023-08-17 05:45:25', '1'),
(32, NULL, 19, 1, '2023-08-17 05:45:37', '2023-08-17 05:45:37', '1'),
(41, NULL, 18, 1, '2023-08-17 06:39:17', '2023-08-17 06:39:17', '1'),
(42, NULL, 17, 1, '2023-08-17 16:16:32', '2023-08-17 16:16:32', '1'),
(43, NULL, 20, 1, '2023-08-17 19:01:37', '2023-08-17 19:01:37', '1'),
(44, NULL, 20, 1, '2023-08-17 19:02:38', '2023-08-17 19:02:38', '1'),
(45, NULL, 20, 1, '2023-08-17 19:03:27', '2023-08-17 19:03:27', '1'),
(50, NULL, 14, 2, '2023-08-17 22:09:58', '2023-08-17 22:09:58', '1'),
(52, NULL, 19, 2, '2023-08-17 22:22:36', '2023-08-17 22:22:36', '1'),
(53, NULL, 19, 1, '2023-08-18 01:24:22', '2023-08-18 01:24:22', '1'),
(54, NULL, 20, 1, '2023-08-18 01:38:21', '2023-08-18 01:38:21', '1'),
(55, NULL, 20, 1, '2023-08-18 01:40:17', '2023-08-18 01:40:17', '1'),
(56, NULL, 20, 1, '2023-08-18 01:49:00', '2023-08-18 01:49:00', '1'),
(57, NULL, 19, 4, '2023-08-18 01:49:04', '2023-08-18 01:49:04', '1'),
(59, NULL, 20, 1, '2023-08-18 01:57:41', '2023-08-18 01:57:41', '1'),
(60, NULL, 19, 1, '2023-08-18 02:03:03', '2023-08-18 02:03:03', '1'),
(61, NULL, 19, 1, '2023-08-18 02:10:46', '2023-08-18 02:10:46', '1'),
(62, NULL, 19, 5, '2023-08-18 02:11:04', '2023-08-18 02:11:04', '1'),
(63, NULL, 15, 2, '2023-08-18 02:11:08', '2023-08-18 02:11:08', '1'),
(64, NULL, 19, 3, '2023-08-18 02:57:29', '2023-08-18 02:57:29', '1'),
(65, NULL, 19, 5, '2023-08-18 02:57:41', '2023-08-18 02:57:41', '1'),
(66, NULL, 15, 1, '2023-08-18 03:16:22', '2023-08-18 03:16:22', '1'),
(67, NULL, 18, 1, '2023-08-18 03:17:35', '2023-08-18 03:17:35', '1'),
(68, NULL, 14, 1, '2023-08-18 03:38:04', '2023-08-18 03:38:04', '1'),
(69, NULL, 18, 4, '2023-08-18 04:19:23', '2023-08-18 04:19:23', '1'),
(70, NULL, 14, 1, '2023-08-18 04:57:11', '2023-08-18 04:57:11', '1'),
(71, NULL, 20, 1, '2023-08-18 04:57:35', '2023-08-18 04:57:35', '1'),
(72, NULL, 19, 1, '2023-08-18 05:18:20', '2023-08-18 05:18:20', '1'),
(73, NULL, 19, 1, '2023-08-18 05:19:54', '2023-08-18 05:19:54', '1'),
(92, NULL, 20, 1, '2023-08-18 06:33:08', '2023-08-18 06:33:08', '1'),
(93, NULL, 19, 1, '2023-08-18 06:33:12', '2023-08-18 06:33:12', '1'),
(100, NULL, 20, 1, '2023-08-18 07:01:24', '2023-08-18 07:01:24', '1'),
(101, NULL, 15, 1, '2023-08-18 07:30:18', '2023-08-18 07:30:18', '1'),
(111, NULL, 20, 1, '2023-08-18 14:44:39', '2023-08-18 14:44:39', '1'),
(112, NULL, 18, 1, '2023-08-18 14:44:54', '2023-08-18 14:44:54', '1'),
(113, NULL, 18, 1, '2023-08-18 15:15:22', '2023-08-18 15:15:22', '1'),
(114, NULL, 17, 1, '2023-08-18 15:15:26', '2023-08-18 15:15:26', '1'),
(124, NULL, 19, 1, '2023-08-18 23:12:40', '2023-08-18 23:12:40', '1'),
(129, NULL, 17, 1, '2023-08-19 05:25:34', '2023-08-19 05:25:34', '1'),
(130, NULL, 20, 2, '2023-08-19 05:25:54', '2023-08-19 05:25:54', '1'),
(132, NULL, 18, 18, '2023-08-19 16:54:01', '2023-08-19 16:54:01', '1'),
(133, NULL, 20, 1, '2023-08-19 16:57:11', '2023-08-19 16:57:11', '1'),
(136, NULL, 19, 1, '2023-08-19 17:32:55', '2023-08-19 17:32:55', '1'),
(137, NULL, 20, 1, '2023-08-19 18:09:40', '2023-08-19 18:09:40', '1'),
(138, NULL, 18, 1, '2023-08-19 18:20:23', '2023-08-19 18:20:23', '1'),
(139, NULL, 19, 1, '2023-08-19 19:29:41', '2023-08-19 19:29:41', '1'),
(140, NULL, 18, 1, '2023-08-19 19:29:54', '2023-08-19 19:29:54', '1'),
(141, NULL, 15, 1, '2023-08-19 19:30:08', '2023-08-19 19:30:08', '1'),
(142, NULL, 19, 5, '2023-08-19 19:33:40', '2023-08-19 19:33:40', '1'),
(144, NULL, 19, 1, '2023-08-19 20:11:17', '2023-08-19 20:11:17', '1'),
(145, NULL, 15, 1, '2023-08-19 20:58:26', '2023-08-19 20:58:26', '1'),
(146, NULL, 18, 1, '2023-08-19 21:02:13', '2023-08-19 21:02:13', '1'),
(147, NULL, 19, 1, '2023-08-19 21:04:43', '2023-08-19 21:04:43', '1'),
(148, NULL, 20, 1, '2023-08-19 21:04:52', '2023-08-19 21:04:52', '1'),
(149, NULL, 19, 1, '2023-08-19 21:20:28', '2023-08-19 21:20:28', '1'),
(150, NULL, 20, 1, '2023-08-19 21:46:31', '2023-08-19 21:46:31', '1'),
(151, NULL, 15, 1, '2023-08-19 21:46:38', '2023-08-19 21:46:38', '1'),
(152, NULL, 19, 1, '2023-08-19 21:47:53', '2023-08-19 21:47:53', '1'),
(153, NULL, 19, 1, '2023-08-19 21:48:03', '2023-08-19 21:48:03', '1'),
(154, NULL, 20, 1, '2023-08-19 22:10:57', '2023-08-19 22:10:57', '1'),
(155, NULL, 19, 1, '2023-08-19 22:37:57', '2023-08-19 22:37:57', '1'),
(156, NULL, 20, 1, '2023-08-19 23:21:08', '2023-08-19 23:21:08', '1'),
(157, NULL, 20, 1, '2023-08-19 23:45:33', '2023-08-19 23:45:33', '1'),
(158, NULL, 15, 1, '2023-08-20 00:17:59', '2023-08-20 00:17:59', '1'),
(160, NULL, 20, 1, '2023-08-20 00:37:01', '2023-08-20 00:37:01', '1'),
(161, NULL, 19, 1, '2023-08-20 01:35:09', '2023-08-20 01:35:09', '1'),
(167, NULL, 20, 1, '2023-08-20 02:18:20', '2023-08-20 02:18:20', '1'),
(168, NULL, 20, 2, '2023-08-20 03:02:05', '2023-08-20 03:02:05', '1'),
(169, NULL, 19, 1, '2023-08-20 03:07:57', '2023-08-20 03:07:57', '1'),
(170, NULL, 20, 1, '2023-08-20 03:08:19', '2023-08-20 03:08:19', '1'),
(171, NULL, 20, 1, '2023-08-20 04:30:06', '2023-08-20 04:30:06', '1'),
(172, NULL, 15, 1, '2023-08-20 04:51:54', '2023-08-20 04:51:54', '1'),
(173, NULL, 20, 1, '2023-08-20 05:32:12', '2023-08-20 05:32:12', '1'),
(174, NULL, 18, 1, '2023-08-20 05:38:07', '2023-08-20 05:38:07', '1'),
(175, NULL, 17, 1, '2023-08-20 05:38:10', '2023-08-20 05:38:10', '1'),
(182, NULL, 19, 5, '2023-08-20 05:44:24', '2023-08-20 05:44:24', '1'),
(191, NULL, 18, 2, '2023-08-20 06:12:45', '2023-08-20 06:12:45', '1'),
(192, NULL, 15, 4, '2023-08-20 07:18:22', '2023-08-20 07:18:22', '1'),
(193, NULL, 15, 1, '2023-08-20 07:46:01', '2023-08-20 07:46:01', '1'),
(194, NULL, 15, 1, '2023-08-20 10:27:52', '2023-08-20 10:27:52', '1'),
(195, NULL, 20, 1, '2023-08-20 10:28:22', '2023-08-20 10:28:22', '1'),
(196, NULL, 20, 1, '2023-08-20 15:42:19', '2023-08-20 15:42:19', '1'),
(197, NULL, 20, 1, '2023-08-20 17:32:24', '2023-08-20 17:32:24', '1'),
(198, NULL, 20, 2, '2023-08-20 18:22:09', '2023-08-20 18:22:09', '1'),
(201, NULL, 15, 1, '2023-08-21 06:27:52', '2023-08-21 06:27:52', '1'),
(214, NULL, 19, 1, '2023-08-22 00:30:33', '2023-08-22 00:30:33', '1'),
(215, NULL, 17, 1, '2023-08-22 00:30:53', '2023-08-22 00:30:53', '1'),
(216, NULL, 16, 1, '2023-08-22 00:31:07', '2023-08-22 00:31:07', '1'),
(244, NULL, 18, 1, '2023-08-23 06:08:49', '2023-08-23 06:08:49', '1'),
(246, NULL, 18, 1, '2023-08-26 00:10:01', '2023-08-26 00:10:01', '1'),
(247, NULL, 18, 1, '2023-08-26 02:11:31', '2023-08-26 02:11:31', '1'),
(248, NULL, 18, 1, '2023-08-26 02:11:31', '2023-08-26 02:11:31', '1'),
(249, NULL, 18, 1, '2023-08-26 03:39:54', '2023-08-26 03:39:54', '1'),
(250, NULL, 18, 1, '2023-08-26 04:59:31', '2023-08-26 04:59:31', '1'),
(251, NULL, 15, 1, '2023-08-26 09:40:01', '2023-08-26 09:40:01', '1'),
(252, NULL, 18, 1, '2023-08-26 18:24:18', '2023-08-26 18:24:18', '1'),
(253, NULL, 15, 1, '2023-08-26 18:24:26', '2023-08-26 18:24:26', '1'),
(259, NULL, 18, 1, '2023-08-28 17:44:59', '2023-08-28 17:44:59', '1'),
(260, NULL, 18, 2, '2023-08-28 17:45:14', '2023-08-28 17:45:14', '1'),
(265, NULL, 21, 1, '2024-01-06 16:03:49', '2024-01-06 16:03:49', '1'),
(266, NULL, 21, 1, '2024-01-06 16:04:37', '2024-01-06 16:04:37', '1'),
(267, NULL, 21, 1, '2024-01-06 16:04:40', '2024-01-06 16:04:40', '1'),
(268, NULL, 21, 1, '2024-01-06 16:05:28', '2024-01-06 16:05:28', '1'),
(269, NULL, 21, 1, '2024-01-06 16:06:32', '2024-01-06 16:06:32', '1'),
(270, NULL, 21, 1, '2024-01-06 16:07:15', '2024-01-06 16:07:15', '1'),
(271, NULL, 21, 1, '2024-01-06 16:07:16', '2024-01-06 16:07:16', '1'),
(272, NULL, 21, 1, '2024-01-06 16:08:04', '2024-01-06 16:08:04', '1'),
(273, NULL, 21, 1, '2024-01-06 16:08:22', '2024-01-06 16:08:22', '1'),
(274, NULL, 21, 1, '2024-01-06 16:08:26', '2024-01-06 16:08:26', '1'),
(275, NULL, 21, 1, '2024-01-06 16:08:55', '2024-01-06 16:08:55', '1'),
(276, NULL, 21, 1, '2024-01-06 16:09:06', '2024-01-06 16:09:06', '1'),
(277, NULL, 15, 1, '2024-01-06 16:09:29', '2024-01-06 16:09:29', '1'),
(278, NULL, 21, 1, '2024-01-06 16:09:45', '2024-01-06 16:09:45', '1'),
(279, NULL, 18, 1, '2024-01-06 16:09:51', '2024-01-06 16:09:51', '1'),
(280, NULL, 18, 1, '2024-01-06 16:09:58', '2024-01-06 16:09:58', '1'),
(281, NULL, 17, 1, '2024-01-06 16:10:54', '2024-01-06 16:10:54', '1'),
(282, NULL, 21, 1, '2024-01-06 16:10:56', '2024-01-06 16:10:56', '1'),
(283, NULL, 17, 1, '2024-01-06 16:11:13', '2024-01-06 16:11:13', '1'),
(284, NULL, 21, 1, '2024-01-06 16:15:02', '2024-01-06 16:15:02', '1'),
(285, NULL, 21, 10, '2024-01-06 16:16:03', '2024-01-06 16:16:03', '1'),
(286, NULL, 21, 1, '2024-01-06 16:16:30', '2024-01-06 16:16:30', '1'),
(300, NULL, 16, 1, '2024-03-17 17:28:15', '2024-03-17 17:28:15', '1'),
(316, NULL, 15, 1, '2024-03-24 08:34:19', '2024-03-24 08:34:19', '1');

-- --------------------------------------------------------

--
-- Table structure for table `commissions`
--

CREATE TABLE `commissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `upline_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `downline_user_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `commission_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `commissions`
--

INSERT INTO `commissions` (`id`, `upline_user_id`, `downline_user_id`, `order_id`, `commission_amount`, `created_at`, `updated_at`) VALUES
(18, 3, 4, 63, 3.50, '2024-03-26 02:19:16', '2024-03-26 02:19:16'),
(19, 4, 5, 64, 3.50, '2024-03-26 02:21:23', '2024-03-26 02:21:23'),
(20, 5, 6, 65, 50.00, '2024-03-26 02:23:19', '2024-03-26 02:23:19'),
(21, 3, 4, 66, 14.00, '2024-04-02 06:50:31', '2024-04-02 06:50:31'),
(22, 3, 4, 67, 14.00, '2024-04-02 06:50:31', '2024-04-02 06:50:31'),
(23, 3, 4, 68, 14.00, '2024-04-02 06:50:31', '2024-04-02 06:50:31');

-- --------------------------------------------------------

--
-- Table structure for table `contacts`
--

CREATE TABLE `contacts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contacts`
--

INSERT INTO `contacts` (`id`, `name`, `email`, `subject`, `phone`, `message`, `created_at`, `updated_at`) VALUES
(12, 'Hj', 'hj@hotmail.con', 'Xff', '0164442338', 'Dff', '2023-08-20 09:27:19', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `education`
--

CREATE TABLE `education` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `long_description` text COLLATE utf8mb4_unicode_ci,
  `period` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `education`
--

INSERT INTO `education` (`id`, `title`, `long_description`, `period`, `created_at`, `updated_at`) VALUES
(1, 'SJK (C) Foon Yew 1', '- Completed UPSR at 2012', '2007 - 2012', '2023-08-12 19:03:47', '2023-08-12 19:03:47'),
(2, 'R.E.A.L Seri Cahaya School', '-Completed PT3 at 2015-Completed SPM at 2017-SPM Result : 2A/2B/2C/2D', '2013-2017', '2023-08-12 19:04:51', '2023-08-12 19:04:51'),
(3, 'Sunway College', '-Completion Diploma Programme at 2021 and awarded top 2 winner for the FYP with property website', '2018-2021', '2023-08-12 19:06:13', '2023-08-12 19:06:13'),
(4, 'Asia Pacific University (APU)', '-On Going', '2021 - On Going', '2023-08-12 19:07:13', '2023-08-12 19:07:13');

-- --------------------------------------------------------

--
-- Table structure for table `ewallet_transactions`
--

CREATE TABLE `ewallet_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remarks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ewallet_transactions`
--

INSERT INTO `ewallet_transactions` (`id`, `user_id`, `amount`, `type`, `remarks`, `created_at`, `updated_at`) VALUES
(15, 4, 1000.00, 'credit', 'E-Wallet Top Up Request Approved', '2024-04-02 06:49:42', '2024-04-02 06:49:42'),
(16, 4, 280.00, 'debit', 'Purchase made [Order ID:66]', '2024-04-02 06:50:31', '2024-04-02 06:50:31'),
(17, 4, 280.00, 'debit', 'Purchase made [Order ID:67]', '2024-04-02 06:50:31', '2024-04-02 06:50:31'),
(18, 4, 280.00, 'debit', 'Purchase made [Order ID:68]', '2024-04-02 06:50:31', '2024-04-02 06:50:31');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `footers`
--

CREATE TABLE `footers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_description` text COLLATE utf8mb4_unicode_ci,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facebook` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `twitter` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `copyright` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `home_slides`
--

CREATE TABLE `home_slides` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `home_slide` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `home_slides`
--

INSERT INTO `home_slides` (`id`, `title`, `short_title`, `home_slide`, `video_url`, `created_at`, `updated_at`) VALUES
(1, 'B2B2C eCommerce  Platform', 'This system is to provide the platform for the B2B2C with the dealership features. The current system is only completed at the phase 1 development which means that it will be keeping update on the new features from time to time.', 'upload/home_slide/1787849552790559.jpg', 'https://youtu.be/afL7zroA_MA', '2023-08-13 02:30:05', '2024-01-12 02:23:20');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2014_10_12_000000_create_users_table', 1),
(2, '2014_10_12_100000_create_password_reset_tokens_table', 1),
(3, '2019_08_19_000000_create_failed_jobs_table', 1),
(4, '2019_12_14_000001_create_personal_access_tokens_table', 1),
(5, '2023_04_13_013051_create_home_slides_table', 1),
(6, '2023_04_13_090311_create_abouts_table', 1),
(7, '2023_04_14_025453_create_multi_images_table', 1),
(8, '2023_04_15_072119_create_portfolios_table', 1),
(9, '2023_04_15_140050_create_blog_categories_table', 1),
(10, '2023_04_16_025047_create_blogs_table', 1),
(11, '2023_04_17_023753_create_footers_table', 1),
(12, '2023_04_17_031444_create_contacts_table', 1),
(13, '2023_04_21_071752_create_services_table', 1),
(14, '2023_05_27_022311_add_banned_until_to_users_table', 1),
(15, '2023_06_01_152124_change_column_to_nullable', 1),
(16, '2023_06_01_152156_change_status_to_nullable', 1),
(17, '2023_06_07_135108_add_profile_image_to_users_table', 1),
(18, '2023_06_10_052402_create_networks', 1),
(19, '2023_06_10_095251_remove_referral_code_from_users', 1),
(20, '2023_06_10_100852_set_default_value_for_status', 1),
(21, '2023_06_10_103817_add_profile_image_to_users', 1),
(22, '2023_06_10_105751_set_default_image_for_profile_image', 1),
(23, '2023_06_10_120129_set_default_image_for_profile_image', 1),
(24, '2023_06_10_122337_set_default_image_for_profile_image', 1),
(25, '2023_06_11_034415_add_referral_code_to_users', 1),
(26, '2023_06_14_140817_add__invited__by_to_users', 1),
(27, '2023_06_14_141037_add_foreign__invited__by_to_users', 1),
(28, '2023_06_17_051933_remove__invited__by_name', 1),
(29, '2023_06_18_060109_add_invited_by_to_users', 2),
(30, '2023_06_18_132944_create_referral_table', 2),
(31, '2023_06_19_030019_rename_column_to_referral', 2),
(32, '2023_06_25_095835_create_product_table', 2),
(33, '2023_06_25_110434_add_invited_by_to_users', 3),
(34, '2023_06_26_143401_create_product_categories_table', 3),
(35, '2023_06_27_142027_add_column_to_product', 3),
(36, '2023_06_28_134742_change_all_column_nullable_in_home_slide', 3),
(37, '2023_07_01_035826_create_new_column_to_abouts', 3),
(38, '2023_07_02_012957_create_new_column_for_abouts', 3),
(39, '2023_07_02_050010_create_skills_table', 3),
(40, '2023_07_02_132258_create_carts_table', 3),
(41, '2023_07_07_142053_create_acknowledgement', 3),
(42, '2023_07_08_011933_create_acknowledgement', 4),
(43, '2023_07_08_012317_create_acknowledgement_table', 5),
(44, '2023_07_08_072724_create_education_table', 6),
(45, '2023_07_09_075149_update_carts_user_id_nullable', 7),
(46, '2023_07_10_110341_add_guest_id_to_cart', 8),
(47, '2023_07_10_114454_add_guest_id_to_carts', 9),
(48, '2023_07_10_114824_add_guest_id_to_carts', 10),
(49, '2023_07_14_130723_create_order_items_table', 11),
(50, '2023_07_14_133002_create_orders_table', 12),
(51, '2023_07_15_022417_create_transactions_table', 13),
(52, '2023_07_15_033509_add_payment_proof_to_transactions_table', 14),
(53, '2023_07_15_075049_set_default_image_for_transactions', 15),
(54, '2023_07_15_113159_create_roles_table', 16),
(55, '2023_07_15_122020_add_roles_id_to_users', 17),
(56, '2023_07_16_003733_create_role_user_table', 18),
(57, '2023_07_17_104313_create_order_items_table', 19),
(58, '2023_07_17_104623_create_orders_table', 19),
(59, '2023_07_17_104840_create_transactions_table', 19),
(60, '2023_07_17_105915_add_foreign_order_id_to_orders_items', 20),
(61, '2023_07_19_013114_set_default_value_for_payment_proof_to_transactions', 21),
(62, '2023_07_21_132832_remove_default_value_from_payment_proof', 22),
(63, '2023_07_23_014734_add_status_to_orders_table', 23),
(64, '2023_07_24_103723_add_deleted_at_to_products', 24),
(65, '2023_07_29_033822_add_soft_deletes_to_product_categories', 25),
(66, '2023_07_29_043136_add_soft_deletes_to_blogs_categories', 26),
(67, '2023_07_29_084517_add_soft_deletes_to_users', 27);

-- --------------------------------------------------------

--
-- Table structure for table `multi_images`
--

CREATE TABLE `multi_images` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `multi_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `multi_images`
--

INSERT INTO `multi_images` (`id`, `multi_image`, `created_at`, `updated_at`) VALUES
(1, 'upload/multi/1787849587560383.jpg', '2023-08-12 19:02:55', '2024-01-12 02:23:53'),
(2, 'upload/multi/1774110909702075.jpg', '2023-08-13 02:53:30', NULL),
(3, 'upload/multi/1774111137872447.jpeg', '2023-08-13 02:57:06', NULL),
(4, 'upload/multi/1774111305525162.jpg', '2023-08-13 02:59:46', NULL),
(5, 'upload/multi/1774111375166337.jpg', '2023-08-13 03:00:53', NULL),
(6, 'upload/multi/1774111869858772.jpg', '2023-08-13 03:08:44', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `networks`
--

CREATE TABLE `networks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `referral_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_user_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_proof` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total_amount`, `payment_proof`, `status`, `created_at`, `updated_at`) VALUES
(63, 4, 70.00, 'upload/transactions/1794553425004652.jpg', '1', '2024-03-26 02:18:31', '2024-03-26 02:19:16'),
(64, 5, 70.00, 'upload/transactions/1794553598241860.jpg', '1', '2024-03-26 02:21:16', '2024-03-26 02:21:23'),
(65, 6, 1000.00, 'upload/transactions/1794553715070195.jpg', '2', '2024-03-26 02:23:07', '2024-03-27 03:27:37'),
(66, 4, 280.00, 'upload/default.jpg', '1', '2024-04-02 06:50:31', '2024-04-02 06:50:31'),
(67, 4, 280.00, 'upload/default.jpg', '1', '2024-04-02 06:50:31', '2024-04-02 06:50:31'),
(68, 4, 280.00, 'upload/default.jpg', '1', '2024-04-02 06:50:31', '2024-04-02 06:50:31');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `product_id`, `order_id`, `user_id`, `quantity`, `created_at`, `updated_at`) VALUES
(97, 15, 63, 4, 1, '2024-03-26 02:18:31', '2024-03-26 02:18:31'),
(98, 15, 64, 5, 1, '2024-03-26 02:21:16', '2024-03-26 02:21:16'),
(99, 15, 65, 6, 10, '2024-03-26 02:23:07', '2024-03-26 02:23:07'),
(100, 15, 66, 4, 4, '2024-04-02 06:50:31', '2024-04-02 06:50:31');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `portfolios`
--

CREATE TABLE `portfolios` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `portfolio_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portfolio_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portfolio_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `portfolio_description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `portfolios`
--

INSERT INTO `portfolios` (`id`, `portfolio_name`, `portfolio_title`, `portfolio_image`, `portfolio_description`, `created_at`, `updated_at`) VALUES
(1, 'gdwdwdw', 'gdwdw', 'upload/portfolio/1792759322299537.jpg', 'gdwdwd', '2024-02-29 07:28:46', '2024-03-06 07:02:01');

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_category_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_stock` int(11) DEFAULT NULL,
  `sku` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `long_description` text COLLATE utf8mb4_unicode_ci,
  `product_price` decimal(8,2) DEFAULT NULL,
  `customer_price` decimal(8,2) DEFAULT NULL,
  `product_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`id`, `product_name`, `product_category_id`, `product_stock`, `sku`, `long_description`, `product_price`, `customer_price`, `product_image`, `created_at`, `updated_at`, `weight`, `deleted_at`) VALUES
(1, 'sw', '1', 1, '22', '22', 22.00, NULL, 'upload/product/1774038631536189.jpeg', '2023-08-12 07:44:39', '2023-08-12 18:56:06', 2.00, '2023-08-12 18:56:06'),
(2, 'Sjsi', '1', 22, 'dd', 'dd', 11.00, NULL, 'upload/product/1774109067161061.jpg', '2023-08-13 02:24:12', '2023-08-13 02:28:48', 22.00, '2023-08-13 02:28:48'),
(3, 'Sjsi', '1', 22, 'dd', 'dd', 11.00, NULL, 'upload/product/1774109105418869.jpg', '2023-08-13 02:24:48', '2023-08-13 02:28:51', 22.00, '2023-08-13 02:28:51'),
(4, 'Test', '1', 22, 'M03', '22', 22.00, NULL, 'upload/product/1774109332176454.jpg', '2023-08-13 02:28:24', '2023-08-13 02:28:53', 22.00, '2023-08-13 02:28:53'),
(5, 'frew', '1', 33, '33', '33', 33.00, NULL, 'upload/product/1774121546137811.jpg', '2023-08-13 05:42:32', '2023-08-13 17:49:07', 33.00, '2023-08-13 17:49:07'),
(6, '22', '1', 22, '22', '22', 22.00, NULL, 'upload/product/1774203496601357.jpg', '2023-08-14 03:25:07', '2023-08-14 15:54:18', 22.00, '2023-08-14 15:54:18'),
(7, 'shshs', '1', 33, 'ss', 'ssd', 11.00, NULL, 'upload/product/1774220992984876.jpg', '2023-08-14 08:03:12', '2023-08-14 15:58:39', 11.00, '2023-08-14 15:58:39'),
(8, 'f44', '1', 34, '3', '44', 4.00, NULL, 'upload/product/1774250648392056.jpeg', '2023-08-14 15:54:34', '2023-08-14 15:58:36', 44.00, '2023-08-14 15:58:36'),
(9, 'fre', '2', 3, '33', '33', 33.00, NULL, 'upload/product/1774303917195750.jpg', '2023-08-15 06:01:15', '2023-08-15 17:57:24', 3.00, '2023-08-15 17:57:24'),
(10, 'Testing', '2', 22, '22', '22', 22.00, NULL, 'upload/product/1774348988339361.jpg', '2023-08-15 17:57:38', '2023-08-15 18:36:03', 22.00, '2023-08-15 18:36:03'),
(11, 'fnfne', '2', 339, '333', 'feufe', 333.00, NULL, 'upload/product/1774382137689969.jpg', '2023-08-16 02:44:32', '2023-08-16 02:45:33', 393.00, '2023-08-16 02:45:33'),
(12, 'edewded', '2', 222, 'M04', 'frfr', 33.00, NULL, 'upload/product/1774382414967036.jpg', '2023-08-16 02:48:56', '2023-08-16 16:52:15', 0.40, '2023-08-16 16:52:15'),
(13, '44', '2', 6, '11', '11', 11.00, NULL, 'upload/product/1774439965536550.jpg', '2023-08-16 18:03:41', '2023-08-16 22:06:31', 11.00, '2023-08-16 22:06:31'),
(14, '[Ready Stock] Mini Me Pop Mart Wishes at Your Fingertips Series Revealed Blind Box 泡泡玛特心愿指尖系列确认款盲盒 指尖第二代', '3', 0, 'M01', '<p>Revealed Blind Box - The box is opened to know what&rsquo;s the character inside.</p>\r\n<p>确认款盲盒- 盲盒已经被拆盒但未拆袋</p>\r\n<p>Please be informed that, the figure inside the box may vary as art is not perfect.</p>\r\n<p>通知：未拆袋的娃可能会有官瑕因为没有完美的艺术品喔 😬</p>', 50.00, NULL, 'upload/product/1774455463648386.jpeg', '2023-08-16 22:10:01', '2023-08-18 18:03:41', 0.40, NULL),
(15, '[Ready Stock] Mega Space Molly Mega Collection 100% Blind Box / Revealed Blind Box Ready Stock Malaysia', '3', 2, 'M02', '<p>Mega Space Molly Mega Collection 100% </p>\r\n<p>100% Authentic Blind box </p>\r\n<p>9 Random box get 1 Big box with plastic cover</p>\r\n<p>Blind box : Toys are boxed in a way that the toy inside the box is mystery to you. </p>\r\n<p>Revealed Blind Box - The box is opened to know what&rsquo;s the character inside.</p>\r\n<p>确认款盲盒- 盲盒已经被拆盒但未拆袋 </p>\r\n<p>Please be informed that, the figure inside the box may vary as art is not perfect. 通知：未拆袋的娃可能会有官瑕因为没有完美的艺术品喔 😬 </p>', 70.00, 100.00, 'upload/product/1774455776734687.jpg', '2023-08-16 22:15:00', '2024-04-02 06:50:31', 0.40, NULL),
(16, 'Dimoo Series Revealed Blind box', '3', 0, 'M05', '**Pre order available for Figure that&rsquo;s out of stock', 40.00, NULL, 'upload/product/1774456167853758.jpg', '2023-08-16 22:21:13', '2024-03-23 05:13:40', 0.00, NULL),
(17, '[Ready Stock] Skullpanda Ancient Castle Series Revealed Blind Box Sp密林古堡 确认款盲盒 拆盒未拆袋', '3', 0, 'M06', '<p>Revealed Blind Box - The box is opened to know what&rsquo;s the character inside.</p>\r\n<p>确认款盲盒- 盲盒已经被拆盒但未拆袋</p>\r\n<p>Please be informed that, the figure inside the box may vary as art is not perfect.</p>\r\n<p>通知：未拆袋的娃可能会有官瑕因为没有完美的艺术品喔 😬</p>', 28.00, NULL, 'upload/product/1774456430811566.jpg', '2023-08-16 22:25:23', '2024-03-23 05:13:40', 0.00, NULL),
(18, 'Hirono Mime Series Revealed Blind Box 小野墨剧系列确认款盲盒', '3', 0, 'M07', '<p>Revealed Blind Box - The box is opened to know what&rsquo;s the character inside.</p>\r\n<p>确认款盲盒- 盲盒已经被拆盒但未拆袋</p>\r\n<p>Please be informed that, the figure inside the box may vary as art is not perfect.</p>\r\n<p>通知：未拆袋的娃可能会有官瑕因为没有完美的艺术品喔 😬</p>', 45.00, 55.00, 'upload/product/1774456509272459.jpg', '2023-08-16 22:26:38', '2024-03-17 06:32:53', 0.00, NULL),
(19, '耙老师打工周系列确认款盲盒 Panda熊猫手办 5.0 1 Rating 1 Sold', '3', -1, 'M08', '<p>**Pre order available for Figure that&rsquo;s out of stock Revealed Blind Box -</p>\r\n<p>The box is opened to know what&rsquo;s the character inside.</p>\r\n<p>确认款盲盒- 盲盒已经被拆盒但未拆袋</p>\r\n<p>Please be informed that, the figure inside the box may vary as art is not perfect.</p>\r\n<p>通知：未拆袋的娃可能会有官瑕因为没有完美的艺术品喔 😬</p>', 28.00, NULL, 'upload/product/1774456592990749.jpg', '2023-08-16 22:27:58', '2024-03-16 04:32:32', 0.40, NULL),
(20, 'Jujutsu Kaisen Series Revealed Blind Box', '3', 0, 'M09', '<p>Revealed Blind Box - The box is opened to know what&rsquo;s the character inside.</p>\r\n<p>确认款盲盒- 盲盒已经被拆盒但未拆袋</p>\r\n<p>Please be informed that, the figure inside the box may vary as art is not perfect. 通知：未拆袋的娃可能会有官瑕因为没有完美的艺术品喔 😬</p>', 24.00, 44.00, 'upload/product/1774456714104514.jpg', '2023-08-16 22:29:53', '2024-03-23 05:31:51', 0.40, NULL),
(21, '混雀', '4', 10, '11', '11', 12.00, 22.00, 'upload/product/1787847969227192.jpg', '2024-01-06 15:59:24', '2024-01-12 01:58:23', 0.00, '2024-01-12 01:58:23');

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`id`, `product_category`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'dw', '2023-08-12 07:44:24', '2023-08-14 17:25:28', '2023-08-14 17:25:28'),
(2, 'fr', '2023-08-15 06:00:45', '2023-08-16 22:07:48', '2023-08-16 22:07:48'),
(3, 'Games, Books & Hobbies', '2023-08-16 22:08:11', NULL, NULL),
(4, 'Apple watch', '2023-08-18 02:29:31', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `referral`
--

CREATE TABLE `referral` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `upline_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `referral_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `referral`
--

INSERT INTO `referral` (`id`, `user_id`, `upline_user_id`, `referral_code`, `created_at`, `updated_at`) VALUES
(71, 3, NULL, NULL, '2024-03-24 03:57:44', '2024-03-24 03:57:44'),
(72, 4, 3, 'NC0QWDrl', '2024-03-26 02:18:01', '2024-03-26 02:18:01'),
(73, 5, 4, 'EytZg0UT', '2024-03-26 02:20:11', '2024-03-26 02:20:11'),
(74, 6, 5, '9quNUKWe', '2024-03-26 02:22:34', '2024-03-26 02:22:34');

-- --------------------------------------------------------

--
-- Table structure for table `referral_links`
--

CREATE TABLE `referral_links` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `referral_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `referral_links`
--

INSERT INTO `referral_links` (`id`, `role_id`, `user_id`, `referral_code`, `created_at`, `updated_at`) VALUES
(148, 350, 3, 'NC0QWDrl', '2024-03-24 03:57:44', '2024-03-24 03:57:44'),
(149, 700, 3, '9rjOQQ0r', '2024-03-24 03:57:44', '2024-03-24 03:57:44'),
(150, 350, 4, 'EytZg0UT', '2024-03-26 02:18:01', '2024-03-26 02:18:01'),
(151, 700, 4, '12uXTNil', '2024-03-26 02:18:01', '2024-03-26 02:18:01'),
(152, 350, 5, '574qNiPG', '2024-03-26 02:20:11', '2024-03-26 02:20:11'),
(153, 700, 5, '9quNUKWe', '2024-03-26 02:20:11', '2024-03-26 02:20:11'),
(154, 350, 6, 'Z21M8TDR', '2024-03-26 02:22:34', '2024-03-26 02:22:34'),
(155, 700, 6, 'DS6aWQ5H', '2024-03-26 02:22:34', '2024-03-26 02:22:34');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin', NULL, NULL),
(2, 'subadmin', 'subadmin', NULL, NULL),
(350, 'agent', 'agent', NULL, NULL),
(700, 'customer', 'customer', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `role_user`
--

CREATE TABLE `role_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_user`
--

INSERT INTO `role_user` (`id`, `role_id`, `user_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `service_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `short_description` text COLLATE utf8mb4_unicode_ci,
  `service_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `skills`
--

CREATE TABLE `skills` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `skill` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `skills`
--

INSERT INTO `skills` (`id`, `skill`, `level`, `created_at`, `updated_at`) VALUES
(2, 'Backend Development - Laravel', 70, NULL, NULL),
(3, 'Frontend Development - Laravel', 63, NULL, '2023-08-12 19:02:41'),
(4, 'Mobile Development with Android', 60, NULL, NULL),
(5, 'Mobile Development with IOS', 52, NULL, NULL),
(6, 'Computer Game Development with C#', 55, NULL, NULL),
(7, 'Data Analysis with R', 63, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `payment_proof` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `order_id`, `user_id`, `payment_proof`, `created_at`, `updated_at`) VALUES
(60, 63, 4, 'upload/payment_proof/1794553425266896.jpg', '2024-03-26 02:18:31', '2024-03-26 02:18:31'),
(61, 64, 5, 'upload/payment_proof/1794553598390710.jpg', '2024-03-26 02:21:16', '2024-03-26 02:21:16'),
(62, 65, 6, 'upload/payment_proof/1794553715239117.jpg', '2024-03-26 02:23:07', '2024-03-26 02:23:07'),
(63, 66, 4, NULL, '2024-04-02 06:50:31', '2024-04-02 06:50:31'),
(64, 67, 4, NULL, '2024-04-02 06:50:31', '2024-04-02 06:50:31'),
(65, 68, 4, NULL, '2024-04-02 06:50:31', '2024-04-02 06:50:31');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `profile_image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '/upload/no_image.jpg',
  `referral_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_referral_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invited_by` bigint(20) UNSIGNED DEFAULT NULL,
  `role_id` bigint(20) UNSIGNED DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `username`, `email_verified_at`, `password`, `status`, `remember_token`, `created_at`, `updated_at`, `profile_image`, `referral_code`, `customer_referral_code`, `invited_by`, `role_id`, `deleted_at`) VALUES
(1, 'Sua Kai Young', 'backlight@gmail.com', 'backlight', NULL, '$2y$10$brdo5Hb3FHnXRAqhhWz39uPR22Mq.Dsl.4ZLuY/dQm.Ryb3rdtIEi', '1', NULL, '2023-07-07 16:19:04', '2023-08-19 07:05:24', '1774170155824187.jpg', 'estkaiyg', 'gQ8oL5zI', NULL, 1, NULL),
(2, 'estherly38', 'ahfish2016@gmail.com', 'esther52hz', NULL, '$2y$10$hlACspiqWOKSmSZtCb/bf.1m0yoKNkw4jNmyKmFxN28eLO/UQm7.C', '1', NULL, '2023-08-18 02:23:17', '2023-08-18 02:23:17', '/upload/no_image.jpg', 'Ew1dMuIs', 'Rik4VZdT', NULL, 2, NULL),
(3, 'kaiyoung', 'kaiyoung@gmail.com', 'kaiyoung', NULL, '$2y$10$tzwW7AE/pQvDCnZbAv4gO.TxoS82pXw7kz4axZHPvCKQlLnR7TRkO', '1', NULL, '2024-03-24 03:57:44', '2024-03-26 03:06:51', '1794556466109410.jpg', 'NC0QWDrl', '9rjOQQ0r', NULL, 350, NULL),
(4, 'kaiyoung', 'kaiyoung1@gmail.com', 'kaiyoung1', NULL, '$2y$10$TFjantRBjo7zs9eBW5jimedPKO.JI.RE3gYwWIPBYFEK3akHxRZFa', '1', NULL, '2024-03-26 02:18:01', '2024-03-26 02:18:01', '/upload/no_image.jpg', 'EytZg0UT', '12uXTNil', 3, 350, NULL),
(5, 'kaiyoung', 'kaiyoung2@gmail.com', 'kaiyoung2', NULL, '$2y$10$UjD07ru9NHihHFEGGXY9neF7BdeFcOEbWOj899LZZ7ZTNbRU64bC6', '1', NULL, '2024-03-26 02:20:11', '2024-03-26 02:20:38', '/upload/no_image.jpg', '574qNiPG', '9quNUKWe', 4, 350, NULL),
(6, 'kaiyoung3', 'kaiyoung3@gmail.com', 'kaiyoung3', NULL, '$2y$10$2Ofc6QlSN3BNmv7TbE5Ow.k0zfutXOqxomO6uxCy0PEyi/cFwZsU6', '1', NULL, '2024-03-26 02:22:34', '2024-03-26 02:22:34', '/upload/no_image.jpg', 'Z21M8TDR', 'DS6aWQ5H', 5, 700, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wallets`
--

CREATE TABLE `wallets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `receipt` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wallets`
--

INSERT INTO `wallets` (`id`, `user_id`, `amount`, `receipt`, `status`, `created_at`, `updated_at`) VALUES
(30, 4, 1000.00, 'upload/ewallet/1795204619015332.jpg', '1', '2024-04-02 06:48:57', '2024-04-02 06:49:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `abouts`
--
ALTER TABLE `abouts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `acknowledgement`
--
ALTER TABLE `acknowledgement`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `blogs`
--
ALTER TABLE `blogs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `blog_categories`
--
ALTER TABLE `blog_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `carts_user_id_foreign` (`user_id`),
  ADD KEY `carts_product_id_foreign` (`product_id`);

--
-- Indexes for table `commissions`
--
ALTER TABLE `commissions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `education`
--
ALTER TABLE `education`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ewallet_transactions`
--
ALTER TABLE `ewallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ewallet_transactions_user_id_foreign` (`user_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `footers`
--
ALTER TABLE `footers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `home_slides`
--
ALTER TABLE `home_slides`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `multi_images`
--
ALTER TABLE `multi_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `networks`
--
ALTER TABLE `networks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `orders_user_id_foreign` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_items_product_id_foreign` (`product_id`),
  ADD KEY `order_items_user_id_foreign` (`user_id`),
  ADD KEY `order_items_order_id_foreign` (`order_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `portfolios`
--
ALTER TABLE `portfolios`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `referral`
--
ALTER TABLE `referral`
  ADD PRIMARY KEY (`id`),
  ADD KEY `referral_user_id_foreign` (`user_id`),
  ADD KEY `referral_parent_user_id_foreign` (`upline_user_id`);

--
-- Indexes for table `referral_links`
--
ALTER TABLE `referral_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referral_links_referral_code_unique` (`referral_code`),
  ADD KEY `referral_links_role_id_foreign` (`role_id`),
  ADD KEY `referral_links_user_id_foreign` (`user_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `role_user`
--
ALTER TABLE `role_user`
  ADD PRIMARY KEY (`id`),
  ADD KEY `role_user_role_id_foreign` (`role_id`),
  ADD KEY `role_user_user_id_foreign` (`user_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transactions_order_id_foreign` (`order_id`),
  ADD KEY `transactions_user_id_foreign` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_username_unique` (`username`),
  ADD KEY `users_invited_by_foreign` (`invited_by`),
  ADD KEY `users_role_id_foreign` (`role_id`);

--
-- Indexes for table `wallets`
--
ALTER TABLE `wallets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `wallets_user_id_foreign` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `abouts`
--
ALTER TABLE `abouts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `acknowledgement`
--
ALTER TABLE `acknowledgement`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `blogs`
--
ALTER TABLE `blogs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `blog_categories`
--
ALTER TABLE `blog_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=323;

--
-- AUTO_INCREMENT for table `commissions`
--
ALTER TABLE `commissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `education`
--
ALTER TABLE `education`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `ewallet_transactions`
--
ALTER TABLE `ewallet_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `footers`
--
ALTER TABLE `footers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `home_slides`
--
ALTER TABLE `home_slides`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `multi_images`
--
ALTER TABLE `multi_images`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `networks`
--
ALTER TABLE `networks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `portfolios`
--
ALTER TABLE `portfolios`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `product`
--
ALTER TABLE `product`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `referral`
--
ALTER TABLE `referral`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `referral_links`
--
ALTER TABLE `referral_links`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=156;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=701;

--
-- AUTO_INCREMENT for table `role_user`
--
ALTER TABLE `role_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `skills`
--
ALTER TABLE `skills`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `wallets`
--
ALTER TABLE `wallets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `carts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ewallet_transactions`
--
ALTER TABLE `ewallet_transactions`
  ADD CONSTRAINT `ewallet_transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`),
  ADD CONSTRAINT `order_items_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `referral`
--
ALTER TABLE `referral`
  ADD CONSTRAINT `referral_parent_user_id_foreign` FOREIGN KEY (`upline_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `referral_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `referral_links`
--
ALTER TABLE `referral_links`
  ADD CONSTRAINT `referral_links_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `referral_links_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_user`
--
ALTER TABLE `role_user`
  ADD CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_invited_by_foreign` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `wallets`
--
ALTER TABLE `wallets`
  ADD CONSTRAINT `wallets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
