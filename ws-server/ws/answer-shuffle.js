/**
 * MC option shuffling shared by kahoot_sync (gameplay.js) and duck_race
 * (engines/duck-race.js) — was duplicated in both files and had drifted
 * (duck-race's null-answer handling was missing the {index:-1} guard).
 */
function optionOrderKey(pin, questionId, studentName) {
  return `room:${pin}:option_order:${questionId}:${studentName}`;
}

function shuffleIndices(length) {
  const indices = Array.from({ length }, (_, index) => index);
  for (let i = indices.length - 1; i > 0; i -= 1) {
    const j = Math.floor(Math.random() * (i + 1));
    [indices[i], indices[j]] = [indices[j], indices[i]];
  }
  return indices;
}

/** Map a student's shuffled-option answer back to the original option index for scoring. */
async function mapShuffledAnswer(redis, pin, questionId, studentName, question, answer) {
  if (question.answer_type !== 'mc') {
    return answer;
  }
  if (answer == null) {
    return { index: -1 };
  }

  const orderRaw = await redis.get(optionOrderKey(pin, questionId, studentName));
  if (!orderRaw) {
    return answer;
  }

  const order = JSON.parse(orderRaw);
  const submitted = typeof answer === 'object' && answer !== null && 'index' in answer
    ? answer.index
    : answer;
  const originalIndex = order[Number(submitted)];

  if (typeof answer === 'object' && answer !== null) {
    return { ...answer, index: originalIndex };
  }

  return originalIndex;
}

module.exports = {
  optionOrderKey,
  shuffleIndices,
  mapShuffledAnswer,
};
