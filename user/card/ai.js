// ai.js
export function chooseCardAI(hand, playedCards = [], difficulty = "medium") {
  if (!hand || hand.length === 0) return 0;

  // Sort hand ascending by card value
  const sorted = [...hand].sort((a, b) => a.value - b.value);

  switch (difficulty) {
    case "easy":
      // Random card
      return Math.floor(Math.random() * hand.length);

    case "medium":
      if (playedCards.length > 0) {
        const leadSuit = playedCards[0].card.suit;
        // Try to beat lead suit with smallest winning card
        const winning = sorted.filter(c => c.suit === leadSuit && c.value > playedCards[0].card.value);
        if (winning.length) return hand.findIndex(h => h === winning[0]);
      }
      // Otherwise, play lowest card
      return hand.findIndex(h => h === sorted[0]);

    case "hard":
      if (playedCards.length > 0) {
        const leadSuit = playedCards[0].card.suit;
        // Try to win with lowest card that still beats lead
        const candidates = sorted.filter(c => c.suit === leadSuit && c.value > playedCards[0].card.value);
        if (candidates.length) return hand.findIndex(h => h === candidates[0]);
        // Cannot follow suit, discard lowest card
        return hand.findIndex(h => h === sorted[0]);
      } else {
        // When leading: play medium-high card to try to win without wasting Ace
        const idx = Math.floor(sorted.length * 0.7);
        const pick = sorted[Math.min(idx, sorted.length - 1)];
        return hand.findIndex(h => h === pick);
      }

    default:
      // fallback
      return 0;
  }
}
