import '../models/standalone_models.dart';

class PsychologyScore {
  const PsychologyScore({
    required this.score,
    required this.status,
    required this.explanation,
    required this.warnings,
  });

  final double score;
  final String status;
  final String explanation;
  final List<String> warnings;
}

class PsychologyScoreService {
  const PsychologyScoreService();

  PsychologyScore evaluate(PsychologyCheckIn checkIn) {
    final discipline = _normalize(checkIn.discipline);
    final confidence = _normalize(checkIn.confidence);
    final mood = _moodProfile(checkIn);
    var score = (discipline * .35) + (confidence * .35) + (mood.score * .30);
    final warnings = <String>[];
    var capped = false;

    if (mood.isNegative) {
      warnings.add('Your mood is negative. Reduce risk or pause trading.');
      if (discipline >= 80) {
        warnings.add(
          'High discipline helps, but it cannot fully offset negative emotion.',
        );
      }
      if (confidence >= 80) {
        warnings.add(
          'High confidence does not always mean emotional readiness.',
        );
      }
      if (discipline >= 80 && confidence >= 80) {
        warnings.add(
          'You are disciplined and confident, but your current mood may still affect your trade decisions.',
        );
      }
    }

    if (checkIn.fomo) {
      score -= 10;
      warnings.add('Review your trading plan before entering the market.');
    }
    if (checkIn.revengeUrge) {
      score -= 15;
      warnings.add(
        'Consider reducing lot size or waiting for a clearer mental state.',
      );
    }
    if (checkIn.overconfidence) {
      score -= 8;
      warnings.add('High confidence does not always mean emotional readiness.');
    }

    var cap = mood.cap;
    if (confidence <= 20) {
      cap = cap == null ? 55 : _min(cap, 55);
    }
    if (confidence <= 20 && mood.isNegative) {
      cap = cap == null ? 45 : _min(cap, 45);
    }
    if (checkIn.revengeUrge) {
      cap = cap == null ? 45 : _min(cap, 45);
    }

    score = score.clamp(0, 100).toDouble();
    if (cap != null && score > cap) {
      score = cap.toDouble();
      capped = true;
    }

    return PsychologyScore(
      score: score,
      status: _status(score),
      explanation: capped || mood.isNegative
          ? 'Although discipline and confidence may be strong, your current mood can still affect trading decision quality.'
          : 'Your readiness is based on discipline, confidence, and current mood.',
      warnings: warnings.toSet().toList(growable: false),
    );
  }

  double _normalize(num value) {
    final raw = value.toDouble();
    if (raw <= 10) return (raw * 10).clamp(0, 100).toDouble();
    return raw.clamp(0, 100).toDouble();
  }

  String _status(double score) {
    if (score >= 80) return 'Ready';
    if (score >= 60) return 'Acceptable';
    if (score >= 40) return 'Caution';
    return 'Not Ready';
  }

  _MoodProfile _moodProfile(PsychologyCheckIn checkIn) {
    if (checkIn.revengeUrge) {
      return const _MoodProfile(score: 10, cap: 45, isNegative: true);
    }
    return switch (_canonicalMood(checkIn.emotion)) {
      'great' => const _MoodProfile(score: 100),
      'focused' => const _MoodProfile(score: 90),
      'calm' => const _MoodProfile(score: 85),
      'neutral' => const _MoodProfile(score: 65),
      'excited' => const _MoodProfile(score: 70),
      'anxious' => const _MoodProfile(score: 45, cap: 80, isNegative: true),
      'frustrated' => const _MoodProfile(score: 30, cap: 75, isNegative: true),
      'angry' => const _MoodProfile(score: 20, cap: 60, isNegative: true),
      'fearful' => const _MoodProfile(score: 25, cap: 65, isNegative: true),
      'revenge' => const _MoodProfile(score: 10, cap: 45, isNegative: true),
      _ => const _MoodProfile(score: 65),
    };
  }

  String _canonicalMood(String value) {
    final mood = value.trim().toLowerCase();
    if (mood.contains('great') || mood.contains('很好')) return 'great';
    if (mood.contains('focused')) return 'focused';
    if (mood.contains('calm') || mood.contains('平静')) return 'calm';
    if (mood.contains('neutral') || mood.contains('普通')) return 'neutral';
    if (mood.contains('anxious') || mood.contains('焦虑')) return 'anxious';
    if (mood.contains('frustrated') ||
        mood.contains('discouraged') ||
        mood.contains('沮丧')) {
      return 'frustrated';
    }
    if (mood.contains('angry') || mood.contains('生气')) return 'angry';
    if (mood.contains('fearful') ||
        mood.contains('afraid') ||
        mood.contains('害怕')) {
      return 'fearful';
    }
    if (mood.contains('revenge') || mood.contains('报复性交易')) {
      return 'revenge';
    }
    if (mood.contains('excited')) return 'excited';
    return mood;
  }

  int _min(int left, int right) => left < right ? left : right;
}

class _MoodProfile {
  const _MoodProfile({required this.score, this.cap, this.isNegative = false});

  final double score;
  final int? cap;
  final bool isNegative;
}
