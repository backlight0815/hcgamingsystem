import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/localization/localized_value.dart';
import '../../core/network/api_client.dart';
import '../../models/firestore_helpers.dart';
import '../../models/knowledge_centre_category.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/formatters.dart';

class AdminContentTab extends StatefulWidget {
  const AdminContentTab({super.key, required this.repository});

  final TradingRepository repository;

  @override
  State<AdminContentTab> createState() => _AdminContentTabState();
}

class _AdminContentTabState extends State<AdminContentTab> {
  late Future<Map<String, dynamic>> _future = widget.repository.adminContent();

  void _reload() {
    final nextFuture = widget.repository.adminContent();
    setState(() {
      _future = nextFuture;
    });
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _AdminError(error: snapshot.error, onRetry: _reload);
        }
        final recordings = _maps(snapshot.data!['recordings']);
        final knowledge = _maps(snapshot.data!['knowledge']);
        final communityDocuments = _maps(snapshot.data!['community_documents']);
        return RefreshIndicator(
          onRefresh: () async => _reload(),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
            children: [
              _AdminHeading(
                title: 'Recording classes',
                subtitle: context.l10n.text('{count} published class(es)', {
                  'count': recordings.length,
                }),
                action: FilledButton.tonalIcon(
                  onPressed: () => _editContent(recording: true),
                  icon: const Icon(Icons.add),
                  label: Text(context.l10n.text('Add')),
                ),
              ),
              const SizedBox(height: 8),
              if (recordings.isEmpty)
                const _InlineMessage('No recording classes published yet.'),
              for (final item in recordings)
                _ContentCard(
                  item: item,
                  recording: true,
                  onEdit: () => _editContent(recording: true, item: item),
                  onDelete: () => _deleteContent(
                    collection: 'trading_recordings',
                    item: item,
                  ),
                ),
              const SizedBox(height: 22),
              _AdminHeading(
                title: 'Knowledge centre',
                subtitle: context.l10n.text('{count} published resource(s)', {
                  'count': knowledge.length,
                }),
                action: FilledButton.tonalIcon(
                  onPressed: () => _editContent(recording: false),
                  icon: const Icon(Icons.add),
                  label: Text(context.l10n.text('Add')),
                ),
              ),
              const SizedBox(height: 8),
              if (knowledge.isEmpty)
                const _InlineMessage('No knowledge-centre resources yet.'),
              for (final item in knowledge)
                _ContentCard(
                  item: item,
                  recording: false,
                  onEdit: () => _editContent(recording: false, item: item),
                  onDelete: () => _deleteContent(
                    collection: 'knowledge_centres',
                    item: item,
                  ),
                ),
              const SizedBox(height: 22),
              _AdminHeading(
                title: 'Community documents',
                subtitle: context.l10n.text(
                  '{count} admin-controlled document(s)',
                  {'count': communityDocuments.length},
                ),
                action: FilledButton.tonalIcon(
                  onPressed: () => _editCommunityDocument(),
                  icon: const Icon(Icons.add),
                  label: Text(context.l10n.text('Add')),
                ),
              ),
              const SizedBox(height: 8),
              if (communityDocuments.isEmpty)
                const _InlineMessage('No community documents published.'),
              for (final item in communityDocuments)
                Card(
                  child: ListTile(
                    onTap: () => _openDocument('${item['file_url'] ?? ''}'),
                    leading: const CircleAvatar(
                      child: Icon(Icons.folder_copy_outlined),
                    ),
                    title: Text(
                      '${item['title'] ?? context.l10n.text('Untitled')}',
                    ),
                    subtitle: Text(
                      '${item['community'] ?? context.l10n.text('General')} - ${context.l10n.text(item['status'] == false ? 'Hidden' : 'Published')}',
                    ),
                    trailing: PopupMenuButton<String>(
                      onSelected: (value) => value == 'edit'
                          ? _editCommunityDocument(item)
                          : _deleteContent(
                              collection: 'community_documents',
                              item: item,
                            ),
                      itemBuilder: (_) => [
                        PopupMenuItem(
                          value: 'edit',
                          child: Text(context.l10n.text('Edit')),
                        ),
                        PopupMenuItem(
                          value: 'delete',
                          child: Text(context.l10n.text('Remove')),
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }

  Future<void> _editContent({
    required bool recording,
    Map<String, dynamic>? item,
  }) async {
    final changed = await showDialog<bool>(
      context: context,
      builder: (_) => _ContentDialog(
        repository: widget.repository,
        recording: recording,
        item: item,
      ),
    );
    if (changed == true) _reload();
  }

  Future<void> _deleteContent({
    required String collection,
    required Map<String, dynamic> item,
  }) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.l10n.text('Remove content?')),
        content: Text(
          context.l10n.text('{title} will no longer be available.', {
            'title': '${item['title']}',
          }),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(context.l10n.text('Remove')),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await widget.repository.adminDeleteContent(
        collection: collection,
        id: '${item['id']}',
      );
      _reload();
    } catch (error) {
      if (mounted) _showError(context, error);
    }
  }

  Future<void> _editCommunityDocument([Map<String, dynamic>? item]) async {
    final changed = await showDialog<bool>(
      context: context,
      builder: (_) =>
          _CommunityDocumentDialog(repository: widget.repository, item: item),
    );
    if (changed == true) _reload();
  }

  Future<void> _openDocument(String value) async {
    final uri = Uri.tryParse(value);
    if (uri == null ||
        !const {'http', 'https'}.contains(uri.scheme) ||
        !await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              context.l10n.text('The document link could not be opened.'),
            ),
          ),
        );
      }
    }
  }
}

class _CommunityDocumentDialog extends StatefulWidget {
  const _CommunityDocumentDialog({required this.repository, this.item});

  final TradingRepository repository;
  final Map<String, dynamic>? item;

  @override
  State<_CommunityDocumentDialog> createState() =>
      _CommunityDocumentDialogState();
}

class _CommunityDocumentDialogState extends State<_CommunityDocumentDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _community;
  late final TextEditingController _title;
  late final TextEditingController _description;
  late final TextEditingController _url;
  PlatformFile? _pickedFile;
  late bool _active;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final item = widget.item ?? const <String, dynamic>{};
    _community = TextEditingController(text: '${item['community'] ?? ''}');
    _title = TextEditingController(text: '${item['title'] ?? ''}');
    _description = TextEditingController(text: '${item['description'] ?? ''}');
    _url = TextEditingController(text: '${item['file_url'] ?? ''}');
    _active = item['status'] != false;
  }

  @override
  void dispose() {
    _community.dispose();
    _title.dispose();
    _description.dispose();
    _url.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(
      context.l10n.text(
        widget.item == null ? 'Publish community document' : 'Edit document',
      ),
    ),
    content: Form(
      key: _formKey,
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextFormField(
              controller: _community,
              decoration: InputDecoration(
                labelText: context.l10n.text('Community'),
              ),
              validator: _required,
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: _saving ? null : _pickFile,
              icon: const Icon(Icons.upload_file_outlined),
              label: Text(
                _pickedFile == null
                    ? context.l10n.text('Upload image or PDF')
                    : _pickedFile!.name,
              ),
            ),
            Text(
              context.l10n.text('PDF up to 10 MB · images up to 8 MB'),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _title,
              decoration: InputDecoration(
                labelText: context.l10n.text('Title'),
              ),
              validator: _required,
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _description,
              maxLines: 3,
              decoration: InputDecoration(
                labelText: context.l10n.text('Description'),
              ),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _url,
              keyboardType: TextInputType.url,
              decoration: InputDecoration(
                labelText: context.l10n.text('Secure document URL'),
                helperText: context.l10n.text(
                  'Optional when a file is selected above.',
                ),
              ),
              validator: (value) {
                if (_pickedFile != null) return null;
                final uri = Uri.tryParse((value ?? '').trim());
                return uri != null &&
                        const {'http', 'https'}.contains(uri.scheme)
                    ? null
                    : context.l10n.text('Enter an http or https URL');
              },
            ),
            SwitchListTile.adaptive(
              contentPadding: EdgeInsets.zero,
              value: _active,
              onChanged: _saving
                  ? null
                  : (value) => setState(() => _active = value),
              title: Text(context.l10n.text('Document active')),
            ),
          ],
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: _saving ? null : () => Navigator.pop(context),
        child: Text(context.l10n.text('Cancel')),
      ),
      FilledButton(
        onPressed: _saving ? null : _save,
        child: Text(context.l10n.text(_saving ? 'Saving...' : 'Save')),
      ),
    ],
  );

  String? _required(String? value) =>
      (value ?? '').trim().isEmpty ? context.l10n.text('Required') : null;

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      var fileUrl = _url.text;
      final selected = _pickedFile;
      if (selected != null) {
        final upload = await widget.repository.uploadManagedFile(
          area: 'community_documents',
          file: ApiUploadFile(
            field: 'document',
            filename: selected.name,
            path: selected.path,
            bytes: selected.bytes,
          ),
        );
        fileUrl = '${upload['url']}';
      }
      await widget.repository.adminSaveCommunityDocument(
        id: widget.item?['id']?.toString(),
        community: _community.text,
        title: _title.text,
        description: _description.text,
        fileUrl: fileUrl,
        active: _active,
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _saving = false);
      _showError(context, error);
    }
  }

  Future<void> _pickFile() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'],
      withData: true,
    );
    if (result != null && mounted) {
      setState(() => _pickedFile = result.files.single);
    }
  }
}

class _ContentCard extends StatelessWidget {
  const _ContentCard({
    required this.item,
    required this.recording,
    required this.onEdit,
    required this.onDelete,
  });

  final Map<String, dynamic> item;
  final bool recording;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final active = item['status'] != false;
    final url = recording ? item['video_url'] : item['file_path'];
    final imageUrl = '${item['image_url'] ?? ''}'.trim();
    return Card(
      child: ListTile(
        leading: imageUrl.isEmpty
            ? CircleAvatar(
                child: Icon(
                  recording
                      ? Icons.video_library_outlined
                      : Icons.school_outlined,
                ),
              )
            : ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: Image.network(
                  imageUrl,
                  width: 52,
                  height: 52,
                  fit: BoxFit.cover,
                  errorBuilder: (_, _, _) => const SizedBox.square(
                    dimension: 52,
                    child: Icon(Icons.broken_image_outlined),
                  ),
                ),
              ),
        title: Text(_adminLocalizedText(item, 'title')),
        subtitle: Text(
          [
            active ? 'Published' : 'Hidden',
            if ('$url'.trim().isNotEmpty) '$url',
          ].join(' · '),
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
        trailing: PopupMenuButton<String>(
          onSelected: (value) => value == 'edit' ? onEdit() : onDelete(),
          itemBuilder: (_) => const [
            PopupMenuItem(value: 'edit', child: Text('Edit')),
            PopupMenuItem(value: 'delete', child: Text('Remove')),
          ],
        ),
      ),
    );
  }
}

class _ContentDialog extends StatefulWidget {
  const _ContentDialog({
    required this.repository,
    required this.recording,
    this.item,
  });

  final TradingRepository repository;
  final bool recording;
  final Map<String, dynamic>? item;

  @override
  State<_ContentDialog> createState() => _ContentDialogState();
}

class _ContentDialogState extends State<_ContentDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _title;
  late final TextEditingController _titleZh;
  late final TextEditingController _description;
  late final TextEditingController _descriptionZh;
  late final TextEditingController _primaryUrl;
  late final TextEditingController _downloadUrl;
  late final TextEditingController _source;
  late String _category;
  late String _imageUrl;
  PlatformFile? _pickedImage;
  PlatformFile? _pickedResource;
  late bool _active;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final item = widget.item ?? const <String, dynamic>{};
    _category =
        KnowledgeCentreCategory.bySlug(item['category'])?.slug ??
        KnowledgeCentreCategory.values.first.slug;
    _title = TextEditingController(
      text: _adminLocalizedText(item, 'title', languageCode: 'en'),
    );
    _titleZh = TextEditingController(
      text: _adminLocalizedText(item, 'title', languageCode: 'zh'),
    );
    _description = TextEditingController(
      text: _adminLocalizedText(item, 'description', languageCode: 'en'),
    );
    _descriptionZh = TextEditingController(
      text: _adminLocalizedText(item, 'description', languageCode: 'zh'),
    );
    _primaryUrl = TextEditingController(
      text: '${item[widget.recording ? 'video_url' : 'file_path'] ?? ''}',
    );
    _downloadUrl = TextEditingController(text: '${item['download_url'] ?? ''}');
    _source = TextEditingController(text: '${item['source_name'] ?? ''}');
    _imageUrl = '${item['image_url'] ?? ''}';
    _active = item['status'] != false;
  }

  @override
  void dispose() {
    _title.dispose();
    _titleZh.dispose();
    _description.dispose();
    _descriptionZh.dispose();
    _primaryUrl.dispose();
    _downloadUrl.dispose();
    _source.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(
        context.l10n.text(
          widget.item == null
              ? widget.recording
                    ? 'Publish recording class'
                    : 'Publish knowledge resource'
              : widget.recording
              ? 'Edit recording class'
              : 'Edit knowledge resource',
        ),
      ),
      content: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (!widget.recording) ...[
                DropdownButtonFormField<String>(
                  initialValue: _category,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Category'),
                  ),
                  items: [
                    for (final category in KnowledgeCentreCategory.values)
                      DropdownMenuItem(
                        value: category.slug,
                        child: Text(context.l10n.text(category.labelKey)),
                      ),
                  ],
                  onChanged: _saving
                      ? null
                      : (value) =>
                            setState(() => _category = value ?? _category),
                ),
                const SizedBox(height: 12),
              ],
              TextFormField(
                controller: _title,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Title (English)'),
                ),
                validator: (value) => _pairedLanguageValidator(
                  value,
                  _titleZh,
                  required: true,
                  languageName: 'English',
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _titleZh,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Title (Simplified Chinese)'),
                ),
                validator: (value) => _pairedLanguageValidator(
                  value,
                  _title,
                  required: true,
                  languageName: 'Simplified Chinese',
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _description,
                maxLines: 3,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Description (English)'),
                ),
                validator: (value) => _pairedLanguageValidator(
                  value,
                  _descriptionZh,
                  required: !widget.recording,
                  languageName: 'English',
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _descriptionZh,
                maxLines: 3,
                decoration: InputDecoration(
                  labelText: context.l10n.text(
                    'Description (Simplified Chinese)',
                  ),
                  helperText: context.l10n.text(
                    'Manual Chinese text is required for production.',
                  ),
                ),
                validator: (value) => _pairedLanguageValidator(
                  value,
                  _description,
                  required: !widget.recording,
                  languageName: 'Simplified Chinese',
                ),
              ),
              const SizedBox(height: 12),
              if (!widget.recording) ...[
                Card(
                  clipBehavior: Clip.antiAlias,
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        if (_pickedImage?.bytes != null)
                          ClipRRect(
                            borderRadius: BorderRadius.circular(10),
                            child: Image.memory(
                              _pickedImage!.bytes!,
                              height: 150,
                              fit: BoxFit.cover,
                            ),
                          )
                        else if (_imageUrl.trim().isNotEmpty)
                          ClipRRect(
                            borderRadius: BorderRadius.circular(10),
                            child: Image.network(
                              _imageUrl,
                              height: 150,
                              fit: BoxFit.cover,
                              errorBuilder: (_, _, _) => const SizedBox(
                                height: 100,
                                child: Center(
                                  child: Icon(Icons.broken_image_outlined),
                                ),
                              ),
                            ),
                          ),
                        const SizedBox(height: 10),
                        OutlinedButton.icon(
                          onPressed: _saving ? null : _pickKnowledgeImage,
                          icon: const Icon(Icons.add_photo_alternate_outlined),
                          label: Text(
                            context.l10n.text(
                              _pickedImage == null && _imageUrl.isEmpty
                                  ? 'Upload knowledge image'
                                  : 'Change image',
                            ),
                          ),
                        ),
                        Text(
                          context.l10n.text(
                            'JPG, PNG, WEBP or GIF - maximum 8 MB',
                          ),
                          textAlign: TextAlign.center,
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),
              ],
              OutlinedButton.icon(
                onPressed: _saving ? null : _pickResource,
                icon: const Icon(Icons.picture_as_pdf_outlined),
                label: Text(
                  _pickedResource == null
                      ? context.l10n.text(
                          widget.recording
                              ? 'Upload class material'
                              : 'Upload knowledge PDF/image',
                        )
                      : _pickedResource!.name,
                ),
              ),
              Text(
                context.l10n.text('PDF up to 10 MB - images up to 8 MB'),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _primaryUrl,
                keyboardType: TextInputType.url,
                decoration: InputDecoration(
                  labelText: widget.recording
                      ? context.l10n.text('Video URL')
                      : context.l10n.text(
                          'Related file URL (optional with image)',
                        ),
                  helperText: widget.recording
                      ? context.l10n.text('Secure video link')
                      : context.l10n.text(
                          'Add a PDF/link, or leave empty to open the uploaded image.',
                        ),
                ),
                validator: _url,
              ),
              if (widget.recording) ...[
                const SizedBox(height: 12),
                TextFormField(
                  controller: _downloadUrl,
                  keyboardType: TextInputType.url,
                  decoration: InputDecoration(
                    labelText: context.l10n.text(
                      'Download/material URL (optional)',
                    ),
                  ),
                  validator: _optionalUrl,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _source,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Trainer or source'),
                  ),
                ),
              ],
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                title: Text(context.l10n.text('Visible to eligible traders')),
                value: _active,
                onChanged: _saving
                    ? null
                    : (value) => setState(() => _active = value),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.pop(context),
          child: Text(context.l10n.text('Cancel')),
        ),
        FilledButton(
          onPressed: _saving ? null : _save,
          child: Text(context.l10n.text(_saving ? 'Saving...' : 'Save')),
        ),
      ],
    );
  }

  String? _required(String? value) =>
      (value ?? '').trim().isEmpty ? context.l10n.text('Required') : null;

  String? _pairedLanguageValidator(
    String? value,
    TextEditingController counterpart, {
    required bool required,
    required String languageName,
  }) {
    final current = (value ?? '').trim();
    final other = counterpart.text.trim();
    if (required && current.isEmpty) {
      return context.l10n.text('{label} is required', {
        'label': context.l10n.text(languageName),
      });
    }
    if (current.isEmpty && other.isNotEmpty) {
      return context.l10n.text(
        '{label} is required when the other language is filled',
        {'label': context.l10n.text(languageName)},
      );
    }
    return null;
  }

  String? _url(String? value) {
    if (!widget.recording &&
        (_pickedImage != null ||
            _pickedResource != null ||
            _imageUrl.trim().isNotEmpty)) {
      return _optionalUrl(value);
    }
    final required = _required(value);
    return required ?? _optionalUrl(value);
  }

  String? _optionalUrl(String? value) {
    final text = (value ?? '').trim();
    if (text.isEmpty) return null;
    final uri = Uri.tryParse(text);
    return uri != null && const {'http', 'https'}.contains(uri.scheme)
        ? null
        : context.l10n.text('Enter an http or https URL');
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      var primaryUrl = _primaryUrl.text;
      var downloadUrl = _downloadUrl.text;
      final selectedResource = _pickedResource;
      if (selectedResource != null) {
        final upload = await widget.repository.uploadManagedFile(
          area: widget.recording ? 'recording_materials' : 'knowledge_centre',
          file: ApiUploadFile(
            field: 'document',
            filename: selectedResource.name,
            path: selectedResource.path,
            bytes: selectedResource.bytes,
          ),
        );
        if (widget.recording) {
          downloadUrl = '${upload['url']}';
        } else {
          primaryUrl = '${upload['url']}';
        }
      }
      if (widget.recording) {
        await widget.repository.adminSaveRecording(
          id: widget.item?['id']?.toString(),
          title: _title.text,
          titleZh: _titleZh.text,
          description: _description.text,
          descriptionZh: _descriptionZh.text,
          videoUrl: primaryUrl,
          downloadUrl: downloadUrl,
          sourceName: _source.text,
          active: _active,
        );
      } else {
        final selectedImage = _pickedImage;
        if (selectedImage != null) {
          _imageUrl = await widget.repository.uploadContentImage(
            area: 'knowledge_centre',
            file: ApiUploadFile(
              field: 'image',
              filename: selectedImage.name,
              path: selectedImage.path,
              bytes: selectedImage.bytes,
            ),
          );
        }
        await widget.repository.adminSaveKnowledge(
          id: widget.item?['id']?.toString(),
          category: _category,
          title: _title.text,
          titleZh: _titleZh.text,
          description: _description.text,
          descriptionZh: _descriptionZh.text,
          fileUrl: primaryUrl.trim().isEmpty ? _imageUrl : primaryUrl,
          imageUrl: _imageUrl,
          active: _active,
        );
      }
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _saving = false);
      _showError(context, error);
    }
  }

  Future<void> _pickKnowledgeImage() async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.image,
        withData: true,
      );
      if (result == null || result.files.isEmpty || !mounted) return;
      final file = result.files.single;
      if (file.bytes == null && file.path == null) {
        throw const ApiException('The selected image could not be read.');
      }
      setState(() => _pickedImage = file);
    } catch (error) {
      if (mounted) _showError(context, error);
    }
  }

  Future<void> _pickResource() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'],
      withData: true,
    );
    if (result != null && mounted) {
      setState(() => _pickedResource = result.files.single);
    }
  }
}

class _PerformanceCard extends StatelessWidget {
  const _PerformanceCard({required this.row, required this.showRank});

  final Map<String, dynamic> row;
  final bool showRank;

  @override
  Widget build(BuildContext context) {
    final user = _map(row['user']);
    final status = '${row['prop_firm_review_status'] ?? 'none'}';
    final profit = _double(row['net_profit_loss']);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                if (showRank) ...[
                  CircleAvatar(child: Text('#${row['rank']}')),
                  const SizedBox(width: 10),
                ],
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _userName(user),
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      Text(
                        '${user['email'] ?? ''}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                _Badge(
                  label: _pretty(status),
                  positive: status.contains('approved'),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 18,
              runSpacing: 10,
              children: [
                _Metric(label: 'Trades', value: '${row['total_trades']}'),
                _Metric(
                  label: 'Net P/L',
                  value: formatMoney(profit),
                  positive: profit >= 0,
                ),
                _Metric(label: 'Win rate', value: '${row['win_rate']}%'),
                _Metric(label: 'Growth', value: '${row['growth_percent']}%'),
                _Metric(
                  label: 'Drawdown',
                  value: formatMoney(_double(row['max_drawdown'])),
                ),
                _Metric(
                  label: 'Prop phase',
                  value: '${row['prop_firm_phase']}',
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class AdminLeaderboardTab extends StatefulWidget {
  const AdminLeaderboardTab({super.key, required this.repository});

  final TradingRepository repository;

  @override
  State<AdminLeaderboardTab> createState() => _AdminLeaderboardTabState();
}

class _AdminLeaderboardTabState extends State<AdminLeaderboardTab> {
  late Future<Map<String, dynamic>> _future = _load();

  Future<Map<String, dynamic>> _load() async => {
    'ranking': await widget.repository.adminTraderPerformance(),
    'competitions': await widget.repository.adminCompetitions(),
  };

  void _reload() {
    final nextFuture = _load();
    setState(() {
      _future = nextFuture;
    });
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, dynamic>>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _AdminError(error: snapshot.error, onRetry: _reload);
        }
        final ranking = _maps(snapshot.data!['ranking']);
        final competitions = _maps(snapshot.data!['competitions']);
        return RefreshIndicator(
          onRefresh: () async => _reload(),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 28),
            children: [
              const _AdminHeading(
                title: 'Live trading leaderboard',
                subtitle:
                    'Ranked by growth, win rate, consistency and drawdown',
              ),
              const SizedBox(height: 8),
              if (ranking.isEmpty)
                const _InlineMessage('No eligible traders yet.'),
              for (final row in ranking.take(10))
                _PerformanceCard(row: row, showRank: true),
              const SizedBox(height: 22),
              _AdminHeading(
                title: 'Trading competitions',
                subtitle:
                    '${competitions.length} competition${competitions.length == 1 ? '' : 's'} configured',
                action: FilledButton.tonalIcon(
                  onPressed: () => _editCompetition(),
                  icon: const Icon(Icons.add),
                  label: const Text('Create'),
                ),
              ),
              const SizedBox(height: 8),
              if (competitions.isEmpty)
                const _InlineMessage('No trading competitions configured.'),
              for (final competition in competitions)
                _CompetitionCard(
                  competition: competition,
                  onEdit: () => _editCompetition(competition),
                  onStandings: () => _showStandings(competition),
                  onDelete: () => _deleteCompetition(competition),
                ),
            ],
          ),
        );
      },
    );
  }

  Future<void> _editCompetition([Map<String, dynamic>? competition]) async {
    final changed = await showDialog<bool>(
      context: context,
      builder: (_) => _CompetitionDialog(
        repository: widget.repository,
        competition: competition,
      ),
    );
    if (changed == true) _reload();
  }

  Future<void> _deleteCompetition(Map<String, dynamic> competition) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete competition?'),
        content: Text('“${competition['title']}” will be removed.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await widget.repository.adminDeleteCompetition('${competition['id']}');
      _reload();
    } catch (error) {
      if (mounted) _showError(context, error);
    }
  }

  Future<void> _showStandings(Map<String, dynamic> competition) async {
    final start = firestoreDate(competition['start_at']);
    final end = firestoreDate(competition['end_at']);
    final minimumTrades = _int(competition['minimum_trades']);
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: .82,
        minChildSize: .5,
        maxChildSize: .95,
        builder: (_, controller) => FutureBuilder<List<Map<String, dynamic>>>(
          future: widget.repository.adminTraderPerformance(
            startAt: start,
            endAt: end,
          ),
          builder: (context, snapshot) {
            if (!snapshot.hasData) {
              return const Center(child: CircularProgressIndicator());
            }
            final rows = snapshot.data!
                .where((row) => _int(row['total_trades']) >= minimumTrades)
                .toList();
            for (var index = 0; index < rows.length; index++) {
              rows[index]['rank'] = index + 1;
            }
            return ListView(
              controller: controller,
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
              children: [
                Text(
                  '${competition['title']} standings',
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                Text('Minimum $minimumTrades trades'),
                const SizedBox(height: 12),
                if (rows.isEmpty)
                  const _InlineMessage('No trader has qualified yet.'),
                for (final row in rows)
                  _PerformanceCard(row: row, showRank: true),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _CompetitionCard extends StatelessWidget {
  const _CompetitionCard({
    required this.competition,
    required this.onEdit,
    required this.onStandings,
    required this.onDelete,
  });

  final Map<String, dynamic> competition;
  final VoidCallback onEdit;
  final VoidCallback onStandings;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final start = firestoreDate(competition['start_at']);
    final end = firestoreDate(competition['end_at']);
    final status = '${competition['status'] ?? 'draft'}';
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    '${competition['title']}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
                _Badge(label: _pretty(status), positive: status == 'open'),
                PopupMenuButton<String>(
                  onSelected: (value) =>
                      value == 'edit' ? onEdit() : onDelete(),
                  itemBuilder: (_) => const [
                    PopupMenuItem(value: 'edit', child: Text('Edit')),
                    PopupMenuItem(value: 'delete', child: Text('Delete')),
                  ],
                ),
              ],
            ),
            if (start != null && end != null)
              Text('${formatDate(start)} – ${formatDate(end)}'),
            Text(
              'Prize: ${competition['prize'] ?? '—'} · Minimum trades: ${competition['minimum_trades'] ?? 0}',
            ),
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerRight,
              child: OutlinedButton.icon(
                onPressed: onStandings,
                icon: const Icon(Icons.leaderboard_outlined),
                label: const Text('Standings'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CompetitionDialog extends StatefulWidget {
  const _CompetitionDialog({required this.repository, this.competition});

  final TradingRepository repository;
  final Map<String, dynamic>? competition;

  @override
  State<_CompetitionDialog> createState() => _CompetitionDialogState();
}

class _CompetitionDialogState extends State<_CompetitionDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _title;
  late final TextEditingController _description;
  late final TextEditingController _prize;
  late final TextEditingController _minimumTrades;
  late DateTime _start;
  late DateTime _end;
  late String _status;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    final data = widget.competition ?? const <String, dynamic>{};
    _title = TextEditingController(text: '${data['title'] ?? ''}');
    _description = TextEditingController(text: '${data['description'] ?? ''}');
    _prize = TextEditingController(text: '${data['prize'] ?? ''}');
    _minimumTrades = TextEditingController(
      text: '${data['minimum_trades'] ?? 10}',
    );
    _start = firestoreDate(data['start_at']) ?? DateTime.now();
    _end =
        firestoreDate(data['end_at']) ??
        DateTime.now().add(const Duration(days: 30));
    _status = '${data['status'] ?? 'draft'}';
  }

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _prize.dispose();
    _minimumTrades.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(
        widget.competition == null ? 'Create competition' : 'Edit competition',
      ),
      content: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: _title,
                decoration: const InputDecoration(labelText: 'Title'),
                validator: (value) =>
                    (value ?? '').trim().isEmpty ? 'Required' : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _description,
                maxLines: 3,
                decoration: const InputDecoration(labelText: 'Description'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _prize,
                decoration: const InputDecoration(labelText: 'Prize'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _minimumTrades,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Minimum trades'),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _status,
                decoration: const InputDecoration(labelText: 'Status'),
                items: const [
                  DropdownMenuItem(value: 'draft', child: Text('Draft')),
                  DropdownMenuItem(value: 'open', child: Text('Open')),
                  DropdownMenuItem(value: 'closed', child: Text('Closed')),
                ],
                onChanged: (value) => setState(() => _status = value!),
              ),
              const SizedBox(height: 8),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Start date'),
                subtitle: Text(formatDate(_start)),
                trailing: const Icon(Icons.calendar_month_outlined),
                onTap: () => _pickDate(start: true),
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('End date'),
                subtitle: Text(formatDate(_end)),
                trailing: const Icon(Icons.event_available_outlined),
                onTap: () => _pickDate(start: false),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: _saving ? null : _save,
          child: Text(_saving ? 'Saving…' : 'Save'),
        ),
      ],
    );
  }

  Future<void> _pickDate({required bool start}) async {
    final initial = start ? _start : _end;
    final selected = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2020),
      lastDate: DateTime(2100),
    );
    if (selected == null) return;
    setState(() {
      if (start) {
        _start = selected;
      } else {
        _end = DateTime(
          selected.year,
          selected.month,
          selected.day,
          23,
          59,
          59,
        );
      }
    });
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      await widget.repository.adminSaveCompetition(
        id: widget.competition?['id']?.toString(),
        title: _title.text,
        description: _description.text,
        startAt: _start,
        endAt: _end,
        prize: _prize.text,
        minimumTrades: int.tryParse(_minimumTrades.text) ?? 0,
        status: _status,
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _saving = false);
      _showError(context, error);
    }
  }
}

class _AdminHeading extends StatelessWidget {
  const _AdminHeading({
    required this.title,
    required this.subtitle,
    this.action,
  });

  final String title;
  final String subtitle;
  final Widget? action;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.l10n.text(title),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            Text(
              context.l10n.text(subtitle),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
      ?action,
    ],
  );
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value, this.positive});

  final String label;
  final String value;
  final bool? positive;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        context.l10n.text(label),
        style: Theme.of(context).textTheme.labelSmall,
      ),
      Text(
        value,
        style: TextStyle(
          fontWeight: FontWeight.w800,
          color: positive == null
              ? null
              : positive!
              ? const Color(0xFF17855B)
              : Theme.of(context).colorScheme.error,
        ),
      ),
    ],
  );
}

class _Badge extends StatelessWidget {
  const _Badge({required this.label, required this.positive});

  final String label;
  final bool positive;

  @override
  Widget build(BuildContext context) {
    final color = positive
        ? const Color(0xFF17855B)
        : Theme.of(context).colorScheme.secondary;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        context.l10n.text(label),
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _InlineMessage extends StatelessWidget {
  const _InlineMessage(this.message);

  final String message;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(20),
      child: Center(
        child: Text(context.l10n.text(message), textAlign: TextAlign.center),
      ),
    ),
  );
}

class _AdminError extends StatelessWidget {
  const _AdminError({required this.error, required this.onRetry});

  final Object? error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            Icons.error_outline,
            size: 48,
            color: Theme.of(context).colorScheme.error,
          ),
          const SizedBox(height: 10),
          Text(context.l10n.text('$error'), textAlign: TextAlign.center),
          const SizedBox(height: 12),
          FilledButton.tonal(
            onPressed: onRetry,
            child: Text(context.l10n.text('Try again')),
          ),
        ],
      ),
    ),
  );
}

List<Map<String, dynamic>> _maps(Object? value) => (value as List? ?? const [])
    .whereType<Map>()
    .map((item) => Map<String, dynamic>.from(item))
    .toList();

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

String _adminLocalizedText(
  Map<String, dynamic> item,
  String key, {
  String languageCode = 'en',
}) {
  final value = getLocalizedText(
    item['${key}_i18n'] ?? item[key],
    languageCode,
    fallback: item[key],
  );
  return value.trim().isEmpty ? 'Untitled' : value;
}

String _userName(Map<String, dynamic> user) =>
    '${user['name'] ?? user['username'] ?? 'Trader'}';

int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;

double _double(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;

String _pretty(String value) => value
    .replaceAll('_', ' ')
    .split(' ')
    .where((word) => word.isNotEmpty)
    .map((word) => '${word[0].toUpperCase()}${word.substring(1)}')
    .join(' ');

void _showError(BuildContext context, Object error) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(context.l10n.text('$error')),
      backgroundColor: Theme.of(context).colorScheme.error,
    ),
  );
}
