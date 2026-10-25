import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';

Future<bool> saveMt5RejectedRows({
  required String filename,
  required Uint8List bytes,
}) async {
  await FilePicker.platform.saveFile(fileName: filename, bytes: bytes);
  return true;
}
