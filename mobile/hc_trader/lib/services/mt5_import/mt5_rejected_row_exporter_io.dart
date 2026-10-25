import 'dart:io';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';

Future<bool> saveMt5RejectedRows({
  required String filename,
  required Uint8List bytes,
}) async {
  final path = await FilePicker.platform.saveFile(
    dialogTitle: 'Export rejected MT5 rows',
    fileName: filename,
    type: FileType.custom,
    allowedExtensions: const ['csv'],
    lockParentWindow: true,
  );
  if (path == null) return false;
  await File(path).writeAsBytes(bytes, flush: true);
  return true;
}
