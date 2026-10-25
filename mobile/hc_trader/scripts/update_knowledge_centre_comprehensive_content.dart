import 'dart:io';

Future<void> main(List<String> args) async {
  final projectRoot = File.fromUri(Platform.script).parent.parent.path;
  final nodeArgs = [
    'tools/update_knowledge_centre_comprehensive_content.js',
    ...args,
  ];
  final result = await Process.start(
    'node',
    nodeArgs,
    workingDirectory: projectRoot,
    runInShell: true,
  );
  await Future.wait([
    stdout.addStream(result.stdout),
    stderr.addStream(result.stderr),
  ]);
  exitCode = await result.exitCode;
}
