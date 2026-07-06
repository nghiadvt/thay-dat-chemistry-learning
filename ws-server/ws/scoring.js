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

function checkAnswer(question, answer) {
  switch (question.answer_type) {
    case 'mc': {
      const submitted = typeof answer === 'object' && answer !== null && 'index' in answer
        ? answer.index
        : answer;
      const correct = Number(submitted) === Number(question.correct_index);
      return {
        correct,
        correctAnswer: question.options?.[question.correct_index] ?? question.correct_index,
      };
    }
    case 'formula': {
      const submitted = typeof answer === 'object' && answer !== null && 'text' in answer
        ? answer.text
        : answer;
      const normalized = normalizeFormula(submitted);
      const expected = normalizeFormula(question.correct_answer_normalized);
      return {
        correct: normalized === expected,
        correctAnswer: question.correct_answer_normalized,
      };
    }
    case 'structured': {
      const correct = deepEqualStructured(answer, question.correct_answer);
      return {
        correct,
        correctAnswer: question.correct_answer,
      };
    }
    default:
      return { correct: false, correctAnswer: null };
  }
}

function deepEqualStructured(a, b) {
  return JSON.stringify(sortKeys(a)) === JSON.stringify(sortKeys(b));
}

function sortKeys(value) {
  if (Array.isArray(value)) {
    return value.map(sortKeys);
  }
  if (value && typeof value === 'object') {
    return Object.keys(value)
      .sort()
      .reduce((acc, key) => {
        acc[key] = sortKeys(value[key]);
        return acc;
      }, {});
  }
  return value;
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
  checkAnswer,
  calculateScore,
};
