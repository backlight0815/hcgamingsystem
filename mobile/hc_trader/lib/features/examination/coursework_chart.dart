import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../models/coursework_models.dart';

class CourseworkChart extends StatefulWidget {
  const CourseworkChart({
    super.key,
    required this.candles,
    required this.symbol,
    required this.timeframe,
    required this.marketDate,
    this.zones = const [],
    this.selectedZoneId,
    this.onZoneSelected,
    this.allowFullScreen = true,
  });

  final List<CourseworkChartCandle> candles;
  final String symbol;
  final String timeframe;
  final DateTime? marketDate;
  final List<CourseworkAnswerZone> zones;
  final String? selectedZoneId;
  final ValueChanged<String>? onZoneSelected;
  final bool allowFullScreen;

  @override
  State<CourseworkChart> createState() => _CourseworkChartState();
}

class _CourseworkChartState extends State<CourseworkChart> {
  final _transformation = TransformationController();

  @override
  void dispose() {
    _transformation.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Semantics(
      label: '${widget.symbol} ${widget.timeframe} candlestick chart',
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: scheme.surfaceContainerLowest,
          border: Border.all(color: scheme.outlineVariant),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 8, 6, 6),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      '${widget.symbol} · ${widget.timeframe}',
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Reset zoom',
                    onPressed: () => _transformation.value = Matrix4.identity(),
                    icon: const Icon(Icons.center_focus_strong_outlined),
                  ),
                  if (widget.allowFullScreen)
                    IconButton(
                      tooltip: 'Full-screen chart',
                      onPressed: _fullScreen,
                      icon: const Icon(Icons.fullscreen),
                    ),
                ],
              ),
            ),
            if (widget.candles.length < 8)
              SizedBox(
                height: 280,
                child: Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.broken_image_outlined, color: scheme.error),
                      const SizedBox(height: 8),
                      const Text('Chart data could not be loaded.'),
                    ],
                  ),
                ),
              )
            else
              SizedBox(
                height: 300,
                child: ClipRRect(
                  borderRadius: const BorderRadius.vertical(
                    bottom: Radius.circular(16),
                  ),
                  child: InteractiveViewer(
                    transformationController: _transformation,
                    minScale: 1,
                    maxScale: 5,
                    panEnabled: true,
                    scaleEnabled: true,
                    boundaryMargin: const EdgeInsets.all(80),
                    child: GestureDetector(
                      behavior: HitTestBehavior.opaque,
                      onTapUp: _selectZone,
                      child: CustomPaint(
                        size: const Size(920, 300),
                        painter: _CourseworkChartPainter(
                          candles: widget.candles,
                          zones: widget.zones,
                          selectedZoneId: widget.selectedZoneId,
                          scheme: scheme,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  void _selectZone(TapUpDetails details) {
    if (widget.onZoneSelected == null || widget.zones.isEmpty) return;
    final values = widget.candles.expand((item) => [item.high, item.low]);
    final high = values.reduce(math.max);
    final low = values.reduce(math.min);
    final price = high - details.localPosition.dy / 300 * (high - low);
    final zone = widget.zones
        .where(
          (item) => price >= item.minimumPrice && price <= item.maximumPrice,
        )
        .firstOrNull;
    if (zone != null) widget.onZoneSelected!(zone.id);
  }

  Future<void> _fullScreen() async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (context) => Scaffold(
          appBar: AppBar(title: Text('${widget.symbol} · ${widget.timeframe}')),
          body: SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Center(
                child: RotatedBox(
                  quarterTurns:
                      MediaQuery.orientationOf(context) == Orientation.portrait
                      ? 1
                      : 0,
                  child: CourseworkChart(
                    candles: widget.candles,
                    symbol: widget.symbol,
                    timeframe: widget.timeframe,
                    marketDate: widget.marketDate,
                    zones: widget.zones,
                    selectedZoneId: widget.selectedZoneId,
                    onZoneSelected: widget.onZoneSelected,
                    allowFullScreen: false,
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
    if (mounted) setState(() {});
  }
}

class _CourseworkChartPainter extends CustomPainter {
  const _CourseworkChartPainter({
    required this.candles,
    required this.zones,
    required this.selectedZoneId,
    required this.scheme,
  });

  final List<CourseworkChartCandle> candles;
  final List<CourseworkAnswerZone> zones;
  final String? selectedZoneId;
  final ColorScheme scheme;

  @override
  void paint(Canvas canvas, Size size) {
    final plot = Rect.fromLTRB(54, 16, size.width - 16, size.height - 32);
    final high = candles.map((item) => item.high).reduce(math.max);
    final low = candles.map((item) => item.low).reduce(math.min);
    final range = math.max(.000001, high - low);
    double y(double price) => plot.bottom - (price - low) / range * plot.height;
    final grid = Paint()
      ..color = scheme.outlineVariant.withValues(alpha: .55)
      ..strokeWidth = 1;
    final labels = TextPainter(textDirection: TextDirection.ltr);
    for (var index = 0; index <= 5; index++) {
      final price = high - range * index / 5;
      final offset = plot.top + plot.height * index / 5;
      canvas.drawLine(
        Offset(plot.left, offset),
        Offset(plot.right, offset),
        grid,
      );
      labels.text = TextSpan(
        text: price.toStringAsFixed(price.abs() >= 100 ? 2 : 4),
        style: TextStyle(fontSize: 10, color: scheme.onSurfaceVariant),
      );
      labels.layout();
      labels.paint(canvas, Offset(2, offset - labels.height / 2));
    }
    for (final zone in zones) {
      final top = y(zone.maximumPrice);
      final bottom = y(zone.minimumPrice);
      final selected = selectedZoneId == zone.id;
      canvas.drawRect(
        Rect.fromLTRB(plot.left, top, plot.right, bottom),
        Paint()
          ..color = (selected ? scheme.primary : scheme.tertiary).withValues(
            alpha: selected ? .2 : .08,
          ),
      );
      canvas.drawRect(
        Rect.fromLTRB(plot.left, top, plot.right, bottom),
        Paint()
          ..color = selected ? scheme.primary : scheme.tertiary
          ..style = PaintingStyle.stroke
          ..strokeWidth = selected ? 2.5 : 1,
      );
      labels.text = TextSpan(
        text: zone.label,
        style: TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w900,
          color: selected ? scheme.primary : scheme.tertiary,
        ),
      );
      labels.layout();
      labels.paint(canvas, Offset(plot.left + 8, (top + bottom) / 2 - 8));
    }
    final slot = plot.width / candles.length;
    final bodyWidth = math.max(2.0, slot * .58);
    for (final entry in candles.indexed) {
      final candle = entry.$2;
      final x = plot.left + slot * (entry.$1 + .5);
      final rising = candle.close >= candle.open;
      final paint = Paint()
        ..color = rising ? const Color(0xFF16866D) : scheme.error
        ..strokeWidth = 1.2;
      canvas.drawLine(
        Offset(x, y(candle.high)),
        Offset(x, y(candle.low)),
        paint,
      );
      canvas.drawRect(
        Rect.fromLTRB(
          x - bodyWidth / 2,
          math.min(y(candle.open), y(candle.close)),
          x + bodyWidth / 2,
          math.max(y(candle.open), y(candle.close)) + 1,
        ),
        paint,
      );
      if (entry.$1 % math.max(1, candles.length ~/ 5) == 0) {
        labels.text = TextSpan(
          text: '${candle.time.month}/${candle.time.day}',
          style: TextStyle(fontSize: 9, color: scheme.onSurfaceVariant),
        );
        labels.layout();
        labels.paint(canvas, Offset(x - labels.width / 2, plot.bottom + 6));
      }
    }
  }

  @override
  bool shouldRepaint(covariant _CourseworkChartPainter oldDelegate) =>
      oldDelegate.candles != candles ||
      oldDelegate.zones != zones ||
      oldDelegate.selectedZoneId != selectedZoneId ||
      oldDelegate.scheme != scheme;
}
