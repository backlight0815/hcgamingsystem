import 'dart:io';

Future<void> main(List<String> args) async {
  final projectRoot = File.fromUri(Platform.script).parent.parent.path;
  final result = await Process.start(
    'node',
    ['tools/validate_knowledge_centres.js', ...args],
    workingDirectory: projectRoot,
    runInShell: true,
  );
  await Future.wait([
    stdout.addStream(result.stdout),
    stderr.addStream(result.stderr),
  ]);
  exitCode = await result.exitCode;
}
