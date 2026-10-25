import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;

class TradingReportPdfTheme {
  const TradingReportPdfTheme._();

  static final navy = PdfColor.fromHex('#102A43');
  static final navyDark = PdfColor.fromHex('#0B1F33');
  static final teal = PdfColor.fromHex('#0E8F86');
  static final positive = PdfColor.fromHex('#15805D');
  static final negative = PdfColor.fromHex('#C23B36');
  static final warning = PdfColor.fromHex('#B7791F');
  static final border = PdfColor.fromHex('#CBD8E3');
  static final tableHeader = PdfColor.fromHex('#EAF1F6');
  static final muted = PdfColor.fromHex('#61758A');
  static final surface = PdfColor.fromHex('#F7FAFC');
  static final paleTeal = PdfColor.fromHex('#E4F5F2');
  static final paleGreen = PdfColor.fromHex('#E8F5EE');
  static final paleRed = PdfColor.fromHex('#FBECEB');
  static final paleAmber = PdfColor.fromHex('#FFF5DD');

  static const pageMargin = 24.0;
  static const sectionGap = 11.0;
  static const cardPadding = 9.0;
  static const tableCellPadding = 4.0;

  static pw.BoxDecoration card({PdfColor? color}) => pw.BoxDecoration(
    color: color ?? PdfColors.white,
    border: pw.Border.all(color: border, width: .55),
    borderRadius: pw.BorderRadius.circular(5),
  );
}
