import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/localization/publishing_localization.dart';
import '../../core/network/api_client.dart';
import '../../core/security/publishing_authorization.dart';
import '../../models/app_user.dart';
import '../../models/market_analysis_item.dart';
import '../../models/trading_blog_post.dart';
import '../../models/trading_pair.dart';
import '../../models/trading_signal_item.dart';
import '../../models/trading_signal_lifecycle.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/app_feedback.dart';
import '../../shared/content_language_selector.dart';
import '../../shared/financial_pairing_dropdown.dart';
import 'publisher_validation.dart';

Future<void> showSignalPublisher(
  BuildContext context, {
  required AppUser user,
  required TradingRepository repository,
}) async {
  final authorization = PublishingAuthorization(user);
  if (!authorization.canPublishTradingSignal) {
    AppFeedback.error(
      context,
      context.l10n.text(
        'Unable to publish the signal because your account permission could not be verified.',
      ),
    );
    return;
  }
  final messenger = ScaffoldMessenger.of(context);
  final submissionId =
      'mobile-${user.uid}-${DateTime.now().microsecondsSinceEpoch}';
  final title = TextEditingController();
  final titleZh = TextEditingController();
  String? financialPairing;
  final entry = TextEditingController();
  final stopLoss = TextEditingController();
  final targets = TextEditingController();
  final disclaimer = TextEditingController(
    text: 'Trading involves risk. Follow your own risk-management plan.',
  );
  final disclaimerZh = TextEditingController(text: '交易涉及风险。请遵守您自己的风险管理计划。');
  PlatformFile? imageFile;
  var action = 'BUY';
  var orderType = SignalOrderType.marketExecution;
  var risk = 'Medium';
  var primaryLanguage = publishingLanguageForLocale(
    AppLocalizations.of(context).locale,
  );
  var saving = false;
  final formKey = GlobalKey<FormState>();

  try {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: EdgeInsets.fromLTRB(
            20,
            0,
            20,
            MediaQuery.viewInsetsOf(context).bottom + 20,
          ),
          child: Form(
            key: formKey,
            child: ListView(
              shrinkWrap: true,
              children: [
                Text(
                  context.l10n.text('Publish trading signal'),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  context.l10n.text('Publishing as {role}', {
                    'role': context.l10n.roleName(user.roleId),
                  }),
                ),
                const SizedBox(height: 18),
                ContentLanguageSelector(
                  primaryLanguage: primaryLanguage,
                  onChanged: (value) =>
                      setSheetState(() => primaryLanguage = value),
                ),
                const SizedBox(height: 12),
                _LocalizedFieldPair(
                  primaryLanguage: primaryLanguage,
                  englishController: title,
                  chineseController: titleZh,
                  englishLabel: 'Signal title (English)',
                  chineseLabel: 'Signal title (Simplified Chinese)',
                  required: true,
                  icon: Icons.online_prediction,
                ),
                const SizedBox(height: 12),
                StreamBuilder<List<TradingPair>>(
                  stream: repository.tradingPairsStream(),
                  builder: (context, snapshot) {
                    final pairs = snapshot.data?.isNotEmpty == true
                        ? snapshot.data!
                        : TradingPair.defaults;
                    return FinancialPairingDropdown(
                      pairs: pairs,
                      value: financialPairing,
                      enabled: !saving,
                      onChanged: (value) =>
                          setSheetState(() => financialPairing = value),
                    );
                  },
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<SignalOrderType>(
                  initialValue: orderType,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Order type'),
                    helperText: context.l10n.text(
                      'Pending orders remain pending until entry is triggered.',
                    ),
                  ),
                  items: [
                    for (final type in SignalOrderType.values)
                      if (type == SignalOrderType.marketExecution ||
                          type == SignalOrderType.pendingOrder ||
                          action == 'BUY' && type.isBuy ||
                          action == 'SELL' && type.isSell)
                        DropdownMenuItem(
                          value: type,
                          child: Text(context.l10n.text(type.label)),
                        ),
                  ],
                  onChanged: (value) => orderType = value ?? orderType,
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: action,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Action'),
                        ),
                        items: [
                          DropdownMenuItem(
                            value: 'BUY',
                            child: Text(context.l10n.text('Buy')),
                          ),
                          DropdownMenuItem(
                            value: 'SELL',
                            child: Text(context.l10n.text('Sell')),
                          ),
                        ],
                        onChanged: (value) => setSheetState(() {
                          action = value ?? action;
                          if (action == 'BUY' && orderType.isSell ||
                              action == 'SELL' && orderType.isBuy) {
                            orderType = SignalOrderType.marketExecution;
                          }
                        }),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: risk,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Risk'),
                        ),
                        items: [
                          DropdownMenuItem(
                            value: 'Low',
                            child: Text(context.l10n.text('Low')),
                          ),
                          DropdownMenuItem(
                            value: 'Medium',
                            child: Text(context.l10n.text('Medium')),
                          ),
                          DropdownMenuItem(
                            value: 'High',
                            child: Text(context.l10n.text('High')),
                          ),
                        ],
                        onChanged: (value) => risk = value ?? risk,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: entry,
                        textInputAction: TextInputAction.next,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Entry price'),
                        ),
                        validator: (value) => _priceRequired(context, value),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: TextFormField(
                        controller: stopLoss,
                        textInputAction: TextInputAction.next,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Stop loss'),
                        ),
                        validator: (value) => _priceRequired(context, value),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: targets,
                  textInputAction: TextInputAction.next,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Take-profit targets'),
                    hintText: '2350, 2365, 2380',
                    helperText: context.l10n.text(
                      'Separate up to 20 targets with commas',
                    ),
                  ),
                  validator: (value) => _validateTargets(
                    context,
                    action: action,
                    entry: entry.text,
                    stopLoss: stopLoss.text,
                    targets: value,
                  ),
                ),
                const SizedBox(height: 12),
                _ImagePickerCard(
                  imageFile: imageFile,
                  enabled: !saving,
                  onPick: () async {
                    final selected = await _pickImage();
                    if (selected != null && context.mounted) {
                      setSheetState(() => imageFile = selected);
                    }
                  },
                ),
                const SizedBox(height: 12),
                _LocalizedFieldPair(
                  primaryLanguage: primaryLanguage,
                  englishController: disclaimer,
                  chineseController: disclaimerZh,
                  englishLabel: 'Risk note (English)',
                  chineseLabel: 'Risk note (Simplified Chinese)',
                  required: true,
                  minLines: 2,
                  maxLines: 3,
                ),
                const SizedBox(height: 18),
                FilledButton.icon(
                  onPressed: saving
                      ? null
                      : () async {
                          if (!formKey.currentState!.validate()) return;
                          setSheetState(() => saving = true);
                          final successMessage = context.l10n.text(
                            'Signal submitted successfully.',
                          );
                          var uploadedChartImage = '';
                          try {
                            final selectedImage = imageFile;
                            uploadedChartImage = selectedImage == null
                                ? ''
                                : await repository.uploadContentImage(
                                    area: 'trading_signals',
                                    file: ApiUploadFile(
                                      field: 'image',
                                      filename: selectedImage.name,
                                      path: selectedImage.path,
                                      bytes: selectedImage.bytes,
                                    ),
                                  );
                            final titleTranslations = publishingLocalizedMap(
                              english: title.text,
                              chinese: titleZh.text,
                            );
                            final disclaimerTranslations =
                                publishingLocalizedMap(
                                  english: disclaimer.text,
                                  chinese: disclaimerZh.text,
                                );
                            await repository.publishTradingSignal(
                              publisherId: user.uid,
                              idempotencyKey: submissionId,
                              signal: TradingSignalItem(
                                id: '',
                                title: publishingPrimaryValue(
                                  language: primaryLanguage,
                                  english: title.text,
                                  chinese: titleZh.text,
                                ),
                                titleI18n: titleTranslations,
                                tradingPair: financialPairing!,
                                immediateAction: action,
                                immediateActionI18n: _actionI18n(action),
                                entryPrice: entry.text.trim(),
                                stopLoss: stopLoss.text.trim(),
                                targets: _splitTargets(targets.text),
                                disclaimer: publishingPrimaryValue(
                                  language: primaryLanguage,
                                  english: disclaimer.text,
                                  chinese: disclaimerZh.text,
                                ),
                                disclaimerI18n: disclaimerTranslations,
                                riskLevel: risk,
                                riskLevelI18n: _riskI18n(risk),
                                orderType: orderType.value,
                                status: orderType.isPending ? 0 : 1,
                                lifecycleStatus: orderType.isPending
                                    ? SignalLifecycleStatus.pendingEntry.value
                                    : SignalLifecycleStatus.active.value,
                                triggerTime: DateTime.now(),
                                chartImage: uploadedChartImage,
                                primaryLanguage: primaryLanguage.code,
                                availableLanguages:
                                    publishingAvailableLanguages([
                                      titleTranslations,
                                      disclaimerTranslations,
                                    ]),
                              ),
                            );
                            if (sheetContext.mounted) {
                              Navigator.pop(sheetContext);
                            }
                            messenger.showSnackBar(
                              SnackBar(content: Text(successMessage)),
                            );
                          } catch (error) {
                            await repository.deleteContentImage(
                              uploadedChartImage,
                            );
                            if (context.mounted) {
                              setSheetState(() => saving = false);
                              AppFeedback.error(
                                context,
                                error,
                                operation: 'publish signal',
                              );
                            }
                          }
                        },
                  icon: saving
                      ? const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.send_outlined),
                  label: Text(context.l10n.text('Publish signal')),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  } finally {
    title.dispose();
    titleZh.dispose();
    entry.dispose();
    stopLoss.dispose();
    targets.dispose();
    disclaimer.dispose();
    disclaimerZh.dispose();
  }
}

Future<void> showMarketAnalysisPublisher(
  BuildContext context, {
  required AppUser user,
  required TradingRepository repository,
}) async {
  final authorization = PublishingAuthorization(user);
  if (!authorization.canPublishMarketAnalysis) {
    AppFeedback.error(
      context,
      context.l10n.text(
        'Unable to publish the analysis because your account permission could not be verified.',
      ),
    );
    return;
  }
  final title = TextEditingController();
  final titleZh = TextEditingController();
  final market = TextEditingController();
  final overview = TextEditingController();
  final overviewZh = TextEditingController();
  final structure = TextEditingController();
  final structureZh = TextEditingController();
  final zones = TextEditingController();
  final zonesZh = TextEditingController();
  final view = TextEditingController();
  final viewZh = TextEditingController();
  final plan = TextEditingController();
  final planZh = TextEditingController();
  PlatformFile? imageFile;
  var strength = 'Neutral';
  var primaryLanguage = publishingLanguageForLocale(
    AppLocalizations.of(context).locale,
  );
  var saving = false;
  final formKey = GlobalKey<FormState>();

  try {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: EdgeInsets.fromLTRB(
            20,
            0,
            20,
            MediaQuery.viewInsetsOf(context).bottom + 20,
          ),
          child: Form(
            key: formKey,
            child: ListView(
              shrinkWrap: true,
              children: [
                Row(
                  children: [
                    IconButton(
                      tooltip: context.l10n.text('Back'),
                      onPressed: () => Navigator.pop(sheetContext),
                      icon: const Icon(Icons.arrow_back),
                    ),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(
                        context.l10n.text('Publish market analysis'),
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  context.l10n.text('Publishing as {role}', {
                    'role': user.roleName,
                  }),
                ),
                const SizedBox(height: 18),
                ContentLanguageSelector(
                  primaryLanguage: primaryLanguage,
                  onChanged: (value) =>
                      setSheetState(() => primaryLanguage = value),
                ),
                const SizedBox(height: 12),
                _LocalizedFieldPair(
                  primaryLanguage: primaryLanguage,
                  englishController: title,
                  chineseController: titleZh,
                  englishLabel: 'Analysis title (English)',
                  chineseLabel: 'Analysis title (Simplified Chinese)',
                  required: true,
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: market,
                        textCapitalization: TextCapitalization.characters,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Market Coverage'),
                          hintText: context.l10n.text(
                            'Example: XAUUSD, Forex, Indices, Crypto, US Stocks',
                          ),
                        ),
                        validator: (value) => (value ?? '').trim().isEmpty
                            ? context.l10n.text('Required')
                            : null,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        initialValue: strength,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Trend strength'),
                        ),
                        items: [
                          DropdownMenuItem(
                            value: 'Bullish',
                            child: Text(context.l10n.text('Bullish')),
                          ),
                          DropdownMenuItem(
                            value: 'Neutral',
                            child: Text(context.l10n.text('Neutral')),
                          ),
                          DropdownMenuItem(
                            value: 'Bearish',
                            child: Text(context.l10n.text('Bearish')),
                          ),
                        ],
                        onChanged: (value) => strength = value ?? strength,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Card(
                  clipBehavior: Clip.antiAlias,
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        if (imageFile?.bytes != null) ...[
                          ClipRRect(
                            borderRadius: BorderRadius.circular(10),
                            child: Image.memory(
                              imageFile!.bytes!,
                              height: 170,
                              fit: BoxFit.cover,
                            ),
                          ),
                          const SizedBox(height: 12),
                        ],
                        OutlinedButton.icon(
                          onPressed: saving
                              ? null
                              : () async {
                                  final selected = await _pickImage();
                                  if (selected != null && context.mounted) {
                                    setSheetState(() => imageFile = selected);
                                  }
                                },
                          icon: const Icon(Icons.add_photo_alternate_outlined),
                          label: Text(
                            imageFile == null
                                ? context.l10n.text('Add chart image')
                                : context.l10n.text('Change {name}', {
                                    'name': imageFile!.name,
                                  }),
                          ),
                        ),
                        const Offstage(
                          child: Text(
                            'Optional Â· JPG, PNG, WEBP or GIF Â· maximum 8 MB',
                            textAlign: TextAlign.center,
                          ),
                        ),
                        Text(
                          context.l10n.text(
                            'Optional - JPG, PNG, WEBP or GIF - maximum 8 MB',
                          ),
                          textAlign: TextAlign.center,
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                _LocalizedFieldPair(
                  primaryLanguage: primaryLanguage,
                  englishController: overview,
                  chineseController: overviewZh,
                  englishLabel: 'Market overview (English)',
                  chineseLabel: 'Market overview (Simplified Chinese)',
                  required: true,
                  minLines: 2,
                  maxLines: 4,
                ),
                const SizedBox(height: 12),
                _LocalizedFieldPair(
                  primaryLanguage: primaryLanguage,
                  englishController: structure,
                  chineseController: structureZh,
                  englishLabel: 'Trend structure (English)',
                  chineseLabel: 'Trend structure (Simplified Chinese)',
                  minLines: 2,
                  maxLines: 4,
                ),
                const SizedBox(height: 12),
                _LocalizedFieldPair(
                  primaryLanguage: primaryLanguage,
                  englishController: zones,
                  chineseController: zonesZh,
                  englishLabel: 'Key zones (English)',
                  chineseLabel: 'Key zones (Simplified Chinese)',
                  minLines: 2,
                  maxLines: 4,
                ),
                const SizedBox(height: 12),
                _LocalizedFieldPair(
                  primaryLanguage: primaryLanguage,
                  englishController: view,
                  chineseController: viewZh,
                  englishLabel: 'Analyst view (English)',
                  chineseLabel: 'Analyst view (Simplified Chinese)',
                  minLines: 2,
                  maxLines: 4,
                ),
                const SizedBox(height: 12),
                _LocalizedFieldPair(
                  primaryLanguage: primaryLanguage,
                  englishController: plan,
                  chineseController: planZh,
                  englishLabel: 'Trading plan (English)',
                  chineseLabel: 'Trading plan (Simplified Chinese)',
                  required: true,
                  minLines: 2,
                  maxLines: 4,
                ),
                const SizedBox(height: 18),
                FilledButton.icon(
                  onPressed: saving
                      ? null
                      : () async {
                          if (!formKey.currentState!.validate()) return;
                          setSheetState(() => saving = true);
                          var uploadedChartImage = '';
                          try {
                            final selectedImage = imageFile;
                            uploadedChartImage = selectedImage == null
                                ? ''
                                : await repository.uploadContentImage(
                                    area: 'market_analyses',
                                    file: ApiUploadFile(
                                      field: 'image',
                                      filename: selectedImage.name,
                                      path: selectedImage.path,
                                      bytes: selectedImage.bytes,
                                    ),
                                  );
                            final titleTranslations = publishingLocalizedMap(
                              english: title.text,
                              chinese: titleZh.text,
                            );
                            final overviewTranslations = publishingLocalizedMap(
                              english: overview.text,
                              chinese: overviewZh.text,
                            );
                            final structureTranslations =
                                publishingLocalizedMap(
                                  english: structure.text,
                                  chinese: structureZh.text,
                                );
                            final zonesTranslations = publishingLocalizedMap(
                              english: zones.text,
                              chinese: zonesZh.text,
                            );
                            final viewTranslations = publishingLocalizedMap(
                              english: view.text,
                              chinese: viewZh.text,
                            );
                            final planTranslations = publishingLocalizedMap(
                              english: plan.text,
                              chinese: planZh.text,
                            );
                            await repository.publishMarketAnalysis(
                              publisherId: user.uid,
                              analysis: MarketAnalysisItem(
                                id: '',
                                title: publishingPrimaryValue(
                                  language: primaryLanguage,
                                  english: title.text,
                                  chinese: titleZh.text,
                                ),
                                titleI18n: titleTranslations,
                                market: market.text.trim().toUpperCase(),
                                trendStrength: strength,
                                analysisDate: DateTime.now(),
                                marketOverview: publishingPrimaryValue(
                                  language: primaryLanguage,
                                  english: overview.text,
                                  chinese: overviewZh.text,
                                ),
                                marketOverviewI18n: overviewTranslations,
                                trendStructure: publishingPrimaryValue(
                                  language: primaryLanguage,
                                  english: structure.text,
                                  chinese: structureZh.text,
                                ),
                                trendStructureI18n: structureTranslations,
                                keyZones: publishingPrimaryValue(
                                  language: primaryLanguage,
                                  english: zones.text,
                                  chinese: zonesZh.text,
                                ),
                                keyZonesI18n: zonesTranslations,
                                analystView: publishingPrimaryValue(
                                  language: primaryLanguage,
                                  english: view.text,
                                  chinese: viewZh.text,
                                ),
                                analystViewI18n: viewTranslations,
                                tradingPlan: publishingPrimaryValue(
                                  language: primaryLanguage,
                                  english: plan.text,
                                  chinese: planZh.text,
                                ),
                                tradingPlanI18n: planTranslations,
                                outlookImage: uploadedChartImage,
                                primaryLanguage: primaryLanguage.code,
                                availableLanguages:
                                    publishingAvailableLanguages([
                                      titleTranslations,
                                      overviewTranslations,
                                      structureTranslations,
                                      zonesTranslations,
                                      viewTranslations,
                                      planTranslations,
                                    ]),
                              ),
                            );
                            if (sheetContext.mounted) {
                              Navigator.pop(sheetContext);
                            }
                            if (context.mounted) {
                              AppFeedback.success(
                                context,
                                context.l10n.text(
                                  'Analysis published successfully.',
                                ),
                              );
                            }
                          } catch (error) {
                            await repository.deleteContentImage(
                              uploadedChartImage,
                            );
                            if (context.mounted) {
                              setSheetState(() => saving = false);
                              AppFeedback.error(
                                context,
                                error,
                                operation: 'publish analysis',
                              );
                            }
                          }
                        },
                  icon: saving
                      ? const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.publish_outlined),
                  label: Text(context.l10n.text('Publish analysis')),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  } finally {
    title.dispose();
    titleZh.dispose();
    market.dispose();
    overview.dispose();
    overviewZh.dispose();
    structure.dispose();
    structureZh.dispose();
    zones.dispose();
    zonesZh.dispose();
    view.dispose();
    viewZh.dispose();
    plan.dispose();
    planZh.dispose();
  }
}

Future<void> showTradingBlogPublisher(
  BuildContext context, {
  required AppUser user,
  required TradingRepository repository,
}) async {
  final submissionId =
      'blog-mobile-${user.uid}-${DateTime.now().microsecondsSinceEpoch}';
  final title = TextEditingController();
  final excerpt = TextEditingController();
  final content = TextEditingController();
  final tags = TextEditingController();
  PlatformFile? imageFile;
  var uploadedCoverImage = '';
  var category = 'trading_sharing';
  var saving = false;
  final formKey = GlobalKey<FormState>();

  try {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: EdgeInsets.fromLTRB(
            20,
            0,
            20,
            MediaQuery.viewInsetsOf(context).bottom + 20,
          ),
          child: Form(
            key: formKey,
            child: ListView(
              shrinkWrap: true,
              children: [
                Text(
                  context.l10n.text('Publish trading blog'),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  context.l10n.text('Publishing as {role}', {
                    'role': user.roleName,
                  }),
                ),
                const SizedBox(height: 18),
                TextFormField(
                  controller: title,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Blog title (English)'),
                  ),
                  validator: (value) => (value ?? '').trim().isEmpty
                      ? context.l10n.text('English content is required.')
                      : null,
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: category,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Category'),
                  ),
                  items: [
                    for (final item in const [
                      'trading_sharing',
                      'knowledge_sharing',
                      'psychology_sharing',
                      'risk_management',
                      'market_outlook',
                      'funded_trader_journey',
                    ])
                      DropdownMenuItem(
                        value: item,
                        child: Text(
                          context.l10n.text(_blogCategoryLabel(item)),
                        ),
                      ),
                  ],
                  onChanged: (value) => category = value ?? category,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: tags,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Tags (comma separated)'),
                  ),
                ),
                const SizedBox(height: 12),
                _LongField(
                  controller: excerpt,
                  label: context.l10n.text('Summary (English)'),
                ),
                const SizedBox(height: 12),
                _LongField(
                  controller: content,
                  label: context.l10n.text('Article (English)'),
                  validator: (value) => (value ?? '').trim().isEmpty
                      ? context.l10n.text('English content is required.')
                      : null,
                ),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: saving
                      ? null
                      : () async {
                          final selected = await _pickImage();
                          if (selected != null && context.mounted) {
                            setSheetState(() {
                              imageFile = selected;
                              uploadedCoverImage = '';
                            });
                          }
                        },
                  icon: const Icon(Icons.add_photo_alternate_outlined),
                  label: Text(
                    imageFile == null
                        ? context.l10n.text('Add cover image')
                        : context.l10n.text('Change {name}', {
                            'name': imageFile!.name,
                          }),
                  ),
                ),
                const SizedBox(height: 18),
                FilledButton.icon(
                  onPressed: saving
                      ? null
                      : () async {
                          if (!formKey.currentState!.validate()) return;
                          setSheetState(() => saving = true);
                          try {
                            final selectedImage = imageFile;
                            final coverImage = selectedImage == null
                                ? ''
                                : uploadedCoverImage.isNotEmpty
                                ? uploadedCoverImage
                                : uploadedCoverImage = await repository
                                      .uploadContentImage(
                                        area: 'trading_blogs',
                                        file: ApiUploadFile(
                                          field: 'image',
                                          filename: selectedImage.name,
                                          path: selectedImage.path,
                                          bytes: selectedImage.bytes,
                                        ),
                                      );
                            await repository.publishTradingBlog(
                              publisherId: user.uid,
                              idempotencyKey: submissionId,
                              blog: TradingBlogPost(
                                id: '',
                                title: title.text.trim(),
                                slug: _slug(title.text),
                                category: category,
                                excerpt: excerpt.text.trim(),
                                content: content.text.trim(),
                                coverImage: coverImage,
                                tags: _splitTags(tags.text),
                                status: MarketAnalysisPublicationStatus
                                    .published
                                    .value,
                                publishedAt: DateTime.now(),
                              ),
                            );
                            if (sheetContext.mounted) {
                              Navigator.pop(sheetContext);
                            }
                            if (context.mounted) {
                              AppFeedback.success(
                                context,
                                context.l10n.text(
                                  'Blog published successfully.',
                                ),
                              );
                            }
                          } catch (error) {
                            if (context.mounted) {
                              setSheetState(() => saving = false);
                              AppFeedback.error(
                                context,
                                error,
                                operation: 'publish blog',
                              );
                            }
                          }
                        },
                  icon: saving
                      ? const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.publish_outlined),
                  label: Text(context.l10n.text('Publish blog')),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  } finally {
    title.dispose();
    excerpt.dispose();
    content.dispose();
    tags.dispose();
  }
}

class _LocalizedFieldPair extends StatelessWidget {
  const _LocalizedFieldPair({
    required this.primaryLanguage,
    required this.englishController,
    required this.chineseController,
    required this.englishLabel,
    required this.chineseLabel,
    this.required = false,
    this.icon,
    this.minLines = 1,
    this.maxLines = 1,
  });

  final PublishingLanguage primaryLanguage;
  final TextEditingController englishController;
  final TextEditingController chineseController;
  final String englishLabel;
  final String chineseLabel;
  final bool required;
  final IconData? icon;
  final int minLines;
  final int maxLines;

  @override
  Widget build(BuildContext context) {
    final primaryIsChinese =
        primaryLanguage == PublishingLanguage.simplifiedChinese;
    final primaryController = primaryIsChinese
        ? chineseController
        : englishController;
    final secondaryController = primaryIsChinese
        ? englishController
        : chineseController;
    final primaryLabel = primaryIsChinese ? chineseLabel : englishLabel;
    final secondaryLabel = primaryIsChinese ? englishLabel : chineseLabel;
    final secondaryLanguage = primaryIsChinese
        ? 'English'
        : 'Simplified Chinese';

    Widget field(
      TextEditingController controller,
      String label, {
      required bool isPrimary,
    }) {
      return TextFormField(
        key: ValueKey(
          'publisher-${label.toLowerCase().replaceAll(RegExp(r'[^a-z]+'), '-')}',
        ),
        controller: controller,
        minLines: minLines,
        maxLines: maxLines,
        textInputAction: maxLines == 1
            ? TextInputAction.next
            : TextInputAction.newline,
        decoration: InputDecoration(
          labelText:
              '${context.l10n.text(label)}${isPrimary && required ? ' *' : ''}',
          prefixIcon: icon == null ? null : Icon(icon),
        ),
        validator: !isPrimary || !required
            ? null
            : (value) => (value ?? '').trim().isEmpty
                  ? context.l10n.text('{language} content is required.', {
                      'language': context.l10n.text(
                        primaryIsChinese ? 'Simplified Chinese' : 'English',
                      ),
                    })
                  : null,
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        field(primaryController, primaryLabel, isPrimary: true),
        const SizedBox(height: 6),
        ExpansionTile(
          key: ValueKey('publisher-translation-${primaryLanguage.code}'),
          tilePadding: EdgeInsets.zero,
          childrenPadding: const EdgeInsets.only(bottom: 4),
          title: Text(
            context.l10n.text('Add optional {language} translation', {
              'language': context.l10n.text(secondaryLanguage),
            }),
          ),
          children: [
            field(secondaryController, secondaryLabel, isPrimary: false),
          ],
        ),
      ],
    );
  }
}

class _ImagePickerCard extends StatelessWidget {
  const _ImagePickerCard({
    required this.imageFile,
    required this.enabled,
    required this.onPick,
  });

  final PlatformFile? imageFile;
  final bool enabled;
  final Future<void> Function() onPick;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (imageFile?.bytes != null) ...[
              ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: Image.memory(
                  imageFile!.bytes!,
                  height: 170,
                  fit: BoxFit.cover,
                ),
              ),
              const SizedBox(height: 12),
            ],
            OutlinedButton.icon(
              onPressed: enabled ? onPick : null,
              icon: const Icon(Icons.add_photo_alternate_outlined),
              label: Text(
                imageFile == null
                    ? context.l10n.text('Add chart image')
                    : context.l10n.text('Change {name}', {
                        'name': imageFile!.name,
                      }),
              ),
            ),
            Text(
              context.l10n.text(
                'Optional - JPG, PNG, WEBP or GIF - maximum 8 MB',
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }
}

class _LongField extends StatelessWidget {
  const _LongField({
    required this.controller,
    required this.label,
    this.validator,
  });

  final TextEditingController controller;
  final String label;
  final FormFieldValidator<String>? validator;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      minLines: 2,
      maxLines: 4,
      textCapitalization: TextCapitalization.sentences,
      decoration: InputDecoration(labelText: label, alignLabelWithHint: true),
      validator: validator,
    );
  }
}

String? _priceRequired(BuildContext context, String? value) {
  if ((value ?? '').trim().isEmpty) {
    return context.l10n.text('This field is required');
  }
  return parseTradingPrice(value) == null
      ? context.l10n.text('Enter a valid positive price')
      : null;
}

String? _validateTargets(
  BuildContext context, {
  required String action,
  required String entry,
  required String stopLoss,
  required String? targets,
}) {
  final issue = validateTradingSignalPrices(
    action: action,
    entry: entry,
    stopLoss: stopLoss,
    targets: _splitTargets(targets),
  );
  return switch (issue) {
    null => null,
    TradingSignalValidationIssue.entryRequired ||
    TradingSignalValidationIssue.stopLossRequired => context.l10n.text(
      'Entry price and stop loss are required',
    ),
    TradingSignalValidationIssue.entryInvalid ||
    TradingSignalValidationIssue.stopLossInvalid ||
    TradingSignalValidationIssue.targetInvalid => context.l10n.text(
      'Enter valid positive prices',
    ),
    TradingSignalValidationIssue.targetRequired => context.l10n.text(
      'Add at least one take-profit target',
    ),
    TradingSignalValidationIssue.buyStopLoss => context.l10n.text(
      'For BUY signals, stop loss must be below entry',
    ),
    TradingSignalValidationIssue.sellStopLoss => context.l10n.text(
      'For SELL signals, stop loss must be above entry',
    ),
    TradingSignalValidationIssue.buyTargets => context.l10n.text(
      'For BUY signals, targets must be above entry',
    ),
    TradingSignalValidationIssue.sellTargets => context.l10n.text(
      'For SELL signals, targets must be below entry',
    ),
    TradingSignalValidationIssue.targetOrder => context.l10n.text(
      'Take-profit targets must progress away from entry',
    ),
  };
}

Map<String, String> _actionI18n(String action) {
  final upper = action.toUpperCase();
  return {'en': upper, 'zh': upper == 'SELL' ? '\u5356\u51fa' : '\u4e70\u5165'};
}

Map<String, String> _riskI18n(String risk) {
  return {
    'en': risk,
    'zh': switch (risk.toLowerCase()) {
      'low' => '\u4f4e',
      'high' => '\u9ad8',
      _ => '\u4e2d',
    },
  };
}

List<String> _splitTargets(String? value) => (value ?? '')
    .split(',')
    .map((target) => target.trim())
    .where((target) => target.isNotEmpty)
    .take(20)
    .toList();

List<String> _splitTags(String? value) => (value ?? '')
    .split(',')
    .map((tag) => tag.trim())
    .where((tag) => tag.isNotEmpty)
    .take(12)
    .toList();

String _slug(String value) {
  final slug = value
      .trim()
      .toLowerCase()
      .replaceAll(RegExp(r'[^a-z0-9]+'), '-')
      .replaceAll(RegExp(r'^-+|-+$'), '');
  return slug.isEmpty ? DateTime.now().millisecondsSinceEpoch.toString() : slug;
}

String _blogCategoryLabel(String category) {
  return switch (category) {
    'knowledge_sharing' => 'Knowledge Sharing',
    'psychology_sharing' => 'Trading Psychology',
    'risk_management' => 'Risk Management',
    'market_outlook' => 'Market Outlook',
    'funded_trader_journey' => 'Funded Trader Journey',
    _ => 'Trading Sharing',
  };
}

Future<PlatformFile?> _pickImage() async {
  final result = await FilePicker.platform.pickFiles(
    type: FileType.image,
    withData: true,
  );
  if (result == null || result.files.isEmpty) return null;
  final file = result.files.single;
  if (file.bytes == null && file.path == null) {
    throw const ApiException('The selected image could not be read.');
  }
  return file;
}
