function normalizeFormula(value) {
  if (value == null) return '';
  return String(value)
    .trim()
    .replace(/\s+/g, '')
    .replace(/₂/g, '2')
    .replace(/₃/g, '3')
    .replace(/₄/g, '4')
    .replace(/₅/g, '5')
    .replace(/₆/g, '6')
    .replace(/₇/g, '7')
    .replace(/₈/g, '8')
    .replace(/₉/g, '9')
    .replace(/₀/g, '0')
    .replace(/₁/g, '1')
    .toUpperCase();
}

function normalizeEssay(value) {
  if (value == null) return '';
  return String(value).trim().toLowerCase().replace(/\s+/g, ' ');
}

function checkAnswer(question, answer) {
  switch (question.answer_type) {
    case 'mc': {
      const submitted = typeof answer === 'object' && answer !== null && 'index' in answer
        ? answer.index
        : answer;
      const submittedIndex = Number(submitted);
      // Guard against missing/null/-1 submissions being coerced to 0 and
      // matching a correct_index of 0 (an unanswered question must never score).
      const correct = Number.isInteger(submittedIndex)
        && submittedIndex >= 0
        && submittedIndex === Number(question.correct_index);
      return {
        correct,
        correctAnswer: question.options?.[question.correct_index] ?? question.correct_index,
      };
    }
    case 'essay': {
      const submitted = typeof answer === 'object' && answer !== null && 'text' in answer
        ? answer.text
        : answer;
      const normalized = normalizeEssay(submitted);
      const expected = normalizeEssay(question.correct_answer_normalized);
      return {
        correct: normalized === expected,
        correctAnswer: question.correct_answer_normalized,
      };
    }
    case 'structured': {
      const submitted = typeof answer === 'object' && answer !== null ? answer : {};
      const expected = question.correct_answer || {};
      let correct = true;

      if (expected.coef) {
        for (const [id, ans] of Object.entries(expected.coef)) {
          if (String(submitted.coef?.[id] ?? '') !== String(ans)) {
            correct = false;
            break;
          }
        }
      }

      if (correct && expected.blank) {
        for (const [id, ans] of Object.entries(expected.blank)) {
          if (normalizeFormula(submitted.blank?.[id]) !== normalizeFormula(ans)) {
            correct = false;
            break;
          }
        }
      }

      return {
        correct,
        correctAnswer: expected,
      };
    }
    default:
      return { correct: false, correctAnswer: null };
  }
}

/**
 * score = 1000 × (time_remaining / time_limit) × accuracy_bonus
 * streak ≥3 correct → +50 per subsequent correct question
 */
function calculateScore({ timeLimitSeconds, questionStartedAt, hybridTimestamp, correct, streakBefore }) {
  if (!correct) {
    return { scoreEarned: 0, streakAfter: 0 };
  }

  const timeLimitMs = timeLimitSeconds * 1000;
  const elapsed = Math.max(0, Number(hybridTimestamp) - Number(questionStartedAt));
  const timeRemaining = Math.max(0, timeLimitMs - elapsed);
  const base = Math.round(1000 * (timeRemaining / timeLimitMs));

  const streakAfter = streakBefore + 1;
  const streakBonus = streakAfter >= 4 ? 50 : 0;

  return {
    scoreEarned: base + streakBonus,
    streakAfter,
  };
}

module.exports = {
  normalizeFormula,
  normalizeEssay,
  checkAnswer,
  calculateScore,
};
