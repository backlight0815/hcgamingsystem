import '../models/personality_assessment.dart';
import '../models/personality_type_profile.dart';

class PersonalityAssessmentGuidanceService {
  const PersonalityAssessmentGuidanceService();

  PersonalityAssessmentGuidance generate(PersonalityAssessmentResult result) {
    final energy = result.scoreFor(PersonalityDimension.energy);
    final structure = result.scoreFor(PersonalityDimension.structure);
    final decisions = result.scoreFor(PersonalityDimension.decisions);
    final impulsiveCaution =
        (energy.winningLetter == 'E' && energy.secondPercent < 40) ||
        (structure.winningLetter == 'P' && structure.firstPercent < 40);
    final emotionCaution =
        decisions.winningLetter == 'F' && decisions.firstPercent < 40;
    final maximumRisk = impulsiveCaution || emotionCaution ? .5 : 1.0;
    final maxTrades = impulsiveCaution ? 2 : 3;

    return PersonalityAssessmentGuidance(
      maximumRiskPercent: maximumRisk,
      dailyLossLimitPercent: maximumRisk * 2,
      maximumTradesPerDay: maxTrades,
      suggestions: [
        PersonalityGuidanceSuggestion(
          id: 'fixed-risk',
          titleKey: 'Use a fixed risk ceiling',
          actionKey:
              'Suggestion: risk no more than {risk}% per trade and never increase size to recover a loss.',
          actionValues: {'risk': _number(maximumRisk)},
          cautionKey:
              'This percentage is educational guidance, not a guaranteed safe level.',
        ),
        PersonalityGuidanceSuggestion(
          id: 'daily-loss-limit',
          titleKey: 'Set a daily loss limit',
          actionKey:
              'Suggestion: stop new entries after {loss}% daily loss or two consecutive losses.',
          actionValues: {'loss': _number(maximumRisk * 2)},
          cautionKey:
              'Use the stricter limit when a broker or approved Trading Plan requires one.',
        ),
        PersonalityGuidanceSuggestion(
          id: 'entry-checklist',
          titleKey: 'Require an entry checklist',
          actionKey:
              'Confirm structure, setup, entry, stop loss, target, risk, session, and psychology readiness before entry.',
          cautionKey:
              'Checklist completion cannot guarantee a profitable trade.',
        ),
        PersonalityGuidanceSuggestion(
          id: 'exit-checklist',
          titleKey: 'Predefine the exit decision',
          actionKey:
              'Record invalidation, stop loss, target, and the conditions for an early exit before activating the trade.',
          cautionKey:
              'Market gaps and slippage can still exceed a planned exit.',
        ),
        PersonalityGuidanceSuggestion(
          id: 'cooldown',
          titleKey: 'Use a loss cooldown',
          actionKey:
              'Pause for at least 30 minutes after a loss and complete a psychology check before another entry.',
          cautionKey:
              'A cooldown is a safeguard, not a signal to trade again when time expires.',
        ),
        PersonalityGuidanceSuggestion(
          id: 'frequency',
          titleKey: 'Limit trade frequency',
          actionKey:
              'Suggestion: use {count} trades per day as a ceiling, not a target.',
          actionValues: {'count': '$maxTrades'},
          cautionKey:
              'Taking no trade is valid when no setup meets every rule.',
        ),
        const PersonalityGuidanceSuggestion(
          id: 'fomo',
          titleKey: 'Interrupt FOMO',
          actionKey:
              'If the planned entry is gone, wait for a new confirmed setup instead of chasing price.',
          cautionKey:
              'Missing an opportunity is preferable to abandoning the risk plan.',
        ),
        const PersonalityGuidanceSuggestion(
          id: 'review',
          titleKey: 'Review process on a schedule',
          actionKey:
              'Review execution after each trade, journal patterns weekly, and repeat a psychology check before each session.',
          cautionKey:
              'Do not replace a strategy because of a very small sample.',
        ),
      ],
    );
  }

  String _number(double value) => value == value.roundToDouble()
      ? value.toStringAsFixed(0)
      : value.toStringAsFixed(1);
}
