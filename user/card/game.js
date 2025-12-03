// game.js — Advanced Animated with Sounds
import { chooseCardAI } from './ai.js';

/* ---------- Card & Suit ---------- */
const suits = ['♠','♥','♦','♣'];
const ranks = ['2','3','4','5','6','7','8','9','10','J','Q','K','A'];

function rankToFile(r){
  if(r==='A') return 'ace';
  if(r==='J') return 'jack';
  if(r==='Q') return 'queen';
  if(r==='K') return 'king';
  return r;
}
function suitToFile(s){
  if(s==='♠') return 'spades';
  if(s==='♥') return 'hearts';
  if(s==='♦') return 'diamonds';
  return 'clubs';
}

/* ---------- SOUND ENGINE ---------- */
const sound = {
  deal: new Audio('sounds/deal.mp3'),
  throw: new Audio('sounds/throw.mp3'),
  win: new Audio('sounds/win.mp3'),
  click: new Audio('sounds/click.mp3'),
  gameover: new Audio('sounds/gameover.mp3'),
  pack: new Audio('sounds/pack.mp3'), // NEW: Sound for packing/folding
  show: new Audio('sounds/show.mp3'), // NEW: Sound for show
};
for (let key in sound) sound[key].volume = 0.5;

// Round-specific sounds 1-13
const roundSounds = [];
for(let i=1;i<=13;i++){
  const s = new Audio(`sounds/round${i}.mp3`);
  s.volume = 0.5;
  roundSounds.push(s);
}

function playSound(name){
  const s = sound[name];
  if (!s) return;
  s.currentTime = 0;
  s.play().catch(()=>{});
}

/* ---------- GAME VARIABLES ---------- */
// ADDED wallet, bet, and packed state
let deck = [], players = [], playedCards = [], roundNum = 0, turnIndex = 0, gameActive=false, betPlaced=false;
let currentPot = 0; // NEW: Global pot variable

const logDiv = document.getElementById('log');
const scoreboardDiv = document.getElementById('scoreboard');
const playedArea = document.getElementById('playedArea');
const roundInfo = document.getElementById('roundInfo');
const playerCountEl = document.getElementById('playerCount');
const aiLevelEl = document.getElementById('aiLevel');
const currentPotEl = document.getElementById('currentPot'); // NEW: Pot display element

// Assuming you added a simple input box for betting in HTML
const betInput = document.createElement('input'); 
betInput.type = 'number';
betInput.value = 10; // Default bet
betInput.id = 'betInput';
betInput.min = 1;
// Temporarily adding it to the body for scope, if it's not in the original HTML
document.body.appendChild(betInput).style.display = 'none'; 

// Betting buttons (Assuming the IDs from your HTML)
const betBtn = document.getElementById('betBtn');
const packBtn = document.getElementById('packBtn');
const showBtn = document.getElementById('showBtn'); 

const startBtn = document.getElementById('startBtn');
const dealBtn = document.getElementById('dealBtn');
const autoPlayBtn = document.getElementById('autoPlayBtn');
const resetBtn = document.getElementById('resetBtn');
const configureBtn = document.getElementById('configureBtn');

startBtn.onclick = () => { playSound('click'); startGame(); };
dealBtn.onclick = () => { playSound('click'); dealCardsAnimated(); };
autoPlayBtn.onclick = () => { playSound('click'); autoPlayRound(); };
resetBtn.onclick = () => { playSound('click'); resetGame(); };
configureBtn.onclick = () => { playSound('click'); createConfigUI(); };

// --- NEW BETTING FEATURE HANDLERS START ---
if(betBtn) betBtn.onclick = () => { playSound('click'); placeBet(); }; 
if(packBtn) packBtn.onclick = () => { playSound('click'); packHand(); }; 
if(showBtn) showBtn.onclick = () => { playSound('click'); showCards(); }; 
// --- NEW BETTING FEATURES END ---


function log(msg){
  const d = document.createElement('div');
  d.innerHTML = msg;
  logDiv.prepend(d);
}

/* ---------- DECK (No Change) ---------- */
function buildDeck(){
  deck = [];
  for(const s of suits){
    for(const r of ranks){
      deck.push({
        suit: s,
        rank: r,
        value: (r==='J'?11:r==='Q'?12:r==='K'?13:r==='A'?14:parseInt(r,10)),
        img: `cards/${rankToFile(r)}_of_${suitToFile(s)}.png`
      });
    }
  }
}

function shuffleDeck(){ 
  for(let i=deck.length-1;i>0;i--){ 
    const j=Math.floor(Math.random()*(i+1)); 
    [deck[i],deck[j]]=[deck[j],deck[i]]; 
  } 
}

/* ---------- CONFIG UI (No Change) ---------- */
function createConfigUI(){
  const existing = document.getElementById('configArea');
  if(existing) existing.remove();
  const div = document.createElement('div'); 
  div.id='configArea'; 
  div.style.padding='8px';
  const count = parseInt(playerCountEl.value,10);
  for(let i=0;i<count;i++){
    const sel = document.createElement('select');
    sel.innerHTML = `<option value="human"${i===0?' selected':''}>Human</option><option value="ai"${i>0?' selected':''}>AI</option>`;
    sel.dataset.index = i;
    const label = document.createElement('label'); 
    label.style.marginRight='10px';
    label.appendChild(document.createTextNode(`P${i+1}: `)); 
    label.appendChild(sel);
    div.appendChild(label);
  }
  document.querySelector('.topbar').appendChild(div);
}

/* ---------- GEOMETRY (No Change) ---------- */
function getElementCenter(el){
  const r = el.getBoundingClientRect();
  return { x: r.left + r.width/2, y: r.top + r.height/2 };
}

/* ---------- 3D CARD THROW (No Change) ---------- */
function animateCardThrow(el, p0, p1, p2, duration = 420, bounce = false) {
  return new Promise(resolve => {
    const start = performance.now();
    el.style.position = 'fixed';
    el.style.left = `${p0.x - el.offsetWidth / 2}px`;
    el.style.top = `${p0.y - el.offsetHeight / 2}px`;
    el.style.opacity = '0';
    el.style.pointerEvents = 'none';
    el.style.willChange = 'transform,left,top,opacity,filter';
    function step(now) {
      let t = (now - start) / duration;
      if (t > 1) t = 1;
      const mt = 1 - t;
      const x = mt*mt*p0.x + 2*mt*t*p1.x + t*t*p2.x;
      const y = mt*mt*p0.y + 2*mt*t*p1.y + t*t*p2.y;
      const rotateY = (1 - t) * 180; 
      const rotateZ = Math.sin(t * Math.PI) * 15; 
      const scale = 0.85 + 0.15 * t;
      el.style.left = `${x - el.offsetWidth / 2}px`;
      el.style.top = `${y - el.offsetHeight / 2}px`;
      el.style.transform = `perspective(600px) rotateY(${rotateY}deg) rotateZ(${rotateZ}deg) scale(${scale})`;
      el.style.opacity = t < 0.2 ? t * 2 : (t > 0.9 ? 1 - (t - 0.9)*5 : 1);
      el.style.filter = t < 0.8 ? 'brightness(0.3) blur(1px)' : 'brightness(1) blur(0)';
      if (t < 1) requestAnimationFrame(step);
      else {
        if (bounce) {
          el.animate([{ transform: `translateY(0)` },{ transform: `translateY(-8px)` },{ transform: `translateY(0)` }], { duration: 220, easing: 'ease-out' });
        }
        resolve();
      }
    }
    requestAnimationFrame(step);
  });
}

// --- NEW BETTING FEATURES START ---

function updatePotDisplay() {
    // Recalculate and update the current pot display
    currentPot = players.reduce((sum, p) => sum + p.currentBet, 0);
    if (currentPotEl) {
        currentPotEl.textContent = `₹ ${currentPot.toFixed(2)}`;
    }
}

function placeBet() {
    const humanPlayer = players.find(p => p.type === 'human');
    if (!humanPlayer) return log('Human player not found.');
    if (betPlaced) return log('Bet already placed for this round. Play your card.');
    if (!gameActive) return log('Start the game first.');
    if (humanPlayer.packed) return log('You have already packed this round.');


    const betAmount = parseInt(betInput.value, 10);
    const minBet = 1;
    
    if (isNaN(betAmount) || betAmount < minBet) {
        return log(`Please enter a valid bet amount (Min: ${minBet}).`);
    }
    if (betAmount > humanPlayer.wallet) {
        return log(`Cannot bet ${betAmount}. Wallet balance: ${humanPlayer.wallet}`);
    }

    // Human player bet
    humanPlayer.currentBet += betAmount; // Add to existing bet in the round
    humanPlayer.wallet -= betAmount; // Deduct bet upfront
    
    // AI automatic bet (if they have points and haven't packed)
    players.filter(p => p.type === 'ai' && !p.packed).forEach(ai => {
        const aiBet = ai.currentBet === 0 ? 1 : Math.max(1, Math.min(2, Math.floor(ai.wallet * 0.1))); // Simple AI strategy
        
        if (ai.wallet >= aiBet) {
            ai.currentBet += aiBet; 
            ai.wallet -= aiBet;
        } else if (ai.wallet > 0) {
            // Bet remaining wallet
            ai.currentBet += ai.wallet; 
            ai.wallet = 0;
        } else {
            // AI is broke, it must pack
            ai.packed = true;
            log(`💀 ${ai.name} is out of points and has packed.`);
        }
    });
    
    betPlaced = true; // Mark that the first bet has been placed
    updateScoreboard();
    updatePotDisplay();
    log(`💰 ${humanPlayer.name} raised the bet by ${betAmount} points.`);

    // After betting, the human player can now play a card
    disableBettingControls(true);
    
    // Start the turn sequence
    highlightTurn(turnIndex);
    if(players[turnIndex].type === 'ai'){
        // AI logic to decide to play or pack
        setTimeout(() => {
            const next = players[turnIndex];
            if (next.packed || next.hand.length === 0) {
                // Skip packed/out player
                turnIndex=(turnIndex+1)%players.length;
                highlightTurn(turnIndex);
                return;
            }
            // Simple AI decision: always play the lowest card for now
            const idx = chooseCardAI(next.hand, playedCards, next.difficulty);
            playCard(turnIndex, idx ?? 0);
        }, 700);
    }
}

function packHand() {
    const humanPlayer = players.find(p => p.type === 'human');
    if (!humanPlayer) return log('Human player not found.');
    if (!gameActive) return log('Start the game first.');
    if (humanPlayer.packed) return log('You have already packed this round.');

    humanPlayer.packed = true;
    humanPlayer.hand = []; // Remove hand

    log(`🛑 ${humanPlayer.name} packs the hand.`);
    playSound('pack');
    
    updateScoreboard();
    updateHandUI(); // This will visually hide the hand

    // Check for round end if only one player remains active/unpacked
    checkRoundEndCondition(); 

    // Move turn to next player
    turnIndex=(turnIndex+1)%players.length;
    highlightTurn(turnIndex);
}

function showCards() {
    // Only allow 'Show' if all cards are played in a round (for now, simpler logic)
    // In actual Teen Patti, 'Show' forces an end to the betting phase.
    // Let's implement 'Show' as an 'End Game' condition for testing:
    if (!gameActive) return log('Start the game first.');
    if (players.filter(p => !p.packed).length > 2) {
        return log('Too many players remaining to show cards. Only 2 active players can proceed to show.');
    }
    
    // Simple implementation: Immediately end the round, find the best card *among active players*.
    log('📢 Show initiated!');
    playSound('show');
    
    // This calls endRound, which will determine the winner based on cards played this round
    // If no cards were played (i.e., immediate show), the current implementation breaks.
    // For now, let's ensure we can transition to the end round state cleanly.
    if(playedCards.length > 0) {
        endRound();
    } else {
        log('No cards played yet, but initiating forced show logic is complex.');
    }
}

function checkRoundEndCondition() {
    // Check if only one player is left who hasn't packed
    const activePlayers = players.filter(p => !p.packed);
    if (activePlayers.length === 1) {
        const winner = activePlayers[0];
        log(`👑 ${winner.name} wins the pot due to all other players packing!`);
        
        // Payout logic: Winner gets the entire pot
        winner.wallet += currentPot;
        winner.score++;

        // Reset game state for new round
        setTimeout(() => {
            endRoundCleanUp(winner);
        }, 1000);
        return true;
    }
    return false;
}

function disableBettingControls(disabled) {
    if(betInput) betInput.disabled = disabled;
    if(betBtn) betBtn.disabled = disabled;
    // Pack and Show are separate betting actions, keep them enabled if relevant (e.g. human turn)
    if(packBtn) packBtn.disabled = !gameActive || disabled;
    if(showBtn) showBtn.disabled = !gameActive || disabled;
}

// Helper to clean up the round state after a winner is found
function endRoundCleanUp(winner) {
    // Reset round state
    players.forEach(p => {
        p.currentBet = 0;
        p.packed = false; // Reset packed state
    });
    currentPot = 0;
    betPlaced = false;
    playedCards = [];
    playedArea.innerHTML = '';
    roundNum++;
    roundInfo.textContent = `Round: ${roundNum}`;
    updatePotDisplay();
    updateScoreboard();
    updateHandUI(); // Redraw hands (only if cards remain)

    if(players.every(p => p.hand.length === 0)){
        gameActive=false;
        playSound('gameover');
        showFinalResults();
        return;
    }

    // Start next round with the winner
    const roundSound = roundSounds[roundNum - 1] || sound.click;
    showRoundPopup(`🎮 New Round ${roundNum}`, `Place your bet!`, roundSound, 1400, () => {
        turnIndex = winner.id;
        highlightTurn(turnIndex);
        disableBettingControls(false); // Enable betting for the new round
    });
}
// --- NEW BETTING FEATURES END ---


/* ---------- START GAME ---------- */
function startGame(){
  buildDeck();
  shuffleDeck();
  const count = parseInt(playerCountEl.value,10);
  const configSel = document.querySelectorAll('#configArea select');
  players = [];
  for(let i=0;i<count;i++){
    const type = configSel && configSel[i] ? configSel[i].value : (i===0 ? 'human' : 'ai');
    const name = type==='human' ? `Player ${i+1}` : `AI ${i}`;
    const difficulty = type==='ai' ? aiLevelEl.value : null;
    // Initial Wallet setup
    // IMPORTANT: You should use the PHP variable here if available, but for now we stick to 100.
    players.push({ id:i, name, type, difficulty, hand:[], score:0, wallet: 100, currentBet: 0, packed: false }); 
  }
  log(`🎮 Game started — ${players.length} players. Initial wallet: 100 points.`);
  playedCards = []; roundNum = 1; turnIndex = 0; gameActive = true;
  betPlaced = false; // Reset bet state
  currentPot = 0; // Reset Pot
  updateSlotNames();
  dealCardsAnimated();
}

/* ---------- ANIMATED DEAL (Minor change to end) ---------- */
async function dealCardsAnimated(){
  if(!players.length){ alert('Start the game first'); return; }
  // Reset score, bet, and packed status
  players.forEach(p => { p.hand = []; p.score = 0; p.currentBet = 0; p.packed = false; }); 
  shuffleDeck();

  const tableEl = document.querySelector('.table');
  const tableCenter = getElementCenter(tableEl);
  const slotIds = computeSlotsForPlayers(players.length);

  while(deck.length){
    for(let pi=0; pi<players.length && deck.length; pi++){
      const card = deck.pop();
      const p = players[pi];
      p.hand.push(card);
      const temp = document.createElement('img');
      temp.src = card.img;
      temp.className = 'card-img anim-card';
      temp.style.width = '56px';
      temp.style.height = '78px';
      temp.style.zIndex = 9999;
      document.body.appendChild(temp);
      const slotEl = document.getElementById(slotIds[pi]);
      const target = slotEl ? getElementCenter(slotEl) : { x: tableCenter.x + (pi-1)*80, y: tableCenter.y + 60 + (pi*10) };
      const control = { x: (tableCenter.x + target.x)/2, y: Math.min(tableCenter.y, target.y) - 120 };
      playSound('deal');
      await animateCardThrow(temp, tableCenter, control, target, 300);
      temp.remove();
      await new Promise(r => setTimeout(r, 20));
    }
  }
  updateHandUI();
  updateScoreboard();
  updatePotDisplay(); // Pot is 0 initially
  roundInfo.textContent = `Round: ${roundNum}`;
  
  log('🃏 Cards dealt. Place your bet!');
  betPlaced = false; 

  // Enable betting UI for new round
  disableBettingControls(false);

  // If the first player is an AI and it's not the human player's turn (e.g., P2)
  if(players[turnIndex].type === 'ai'){
      // In a real game, betting starts with the player next to the dealer. 
      // For this simplified version, let the human player bet first if present.
  }
}

/* ---------- SLOT HELPERS (No Change) ---------- */
function computeSlotsForPlayers(count){ return ['slot-bottom','slot-top','slot-left','slot-right'].slice(0,count); }

/* ---------- DRAG AND DROP GLOBALS & FUNCTIONS (No Change) ---------- */
let draggedCard = null;
let offsetX = 0;
let offsetY = 0;
let originalParent = null;

function setupCardDragAndDrop(cardEl) {
    const playerId = parseInt(cardEl.dataset.playerid, 10);
    const cardIndex = parseInt(cardEl.dataset.cardindex, 10);
    const player = players.find(p => p.id === playerId);
    
    if (!player || player.type !== 'human') return;

    const startDrag = (e) => {
        // Check if bet is placed AND it's the player's turn
        if (!gameActive || players[turnIndex].id !== playerId || !betPlaced || player.packed) return; 

        draggedCard = cardEl;
        originalParent = cardEl.parentElement;
        cardEl.classList.add('dragging');

        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        
        const rect = cardEl.getBoundingClientRect();
        offsetX = clientX - rect.left;
        offsetY = clientY - rect.top;

        document.body.appendChild(cardEl);
        cardEl.style.position = 'fixed';
        cardEl.style.zIndex = '99999';

        if (e.touches) e.preventDefault();
    };

    const moveDrag = (e) => {
        if (!draggedCard) return;

        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;

        draggedCard.style.left = (clientX - offsetX) + 'px';
        draggedCard.style.top = (clientY - offsetY) + 'px';
        
        if (e.touches) e.preventDefault();
    };

    const endDrag = () => {
        if (!draggedCard) return;

        const cardRect = draggedCard.getBoundingClientRect();
        const playedRect = playedArea.getBoundingClientRect();
        
        const currentDraggedCard = draggedCard;
        const currentOriginalParent = originalParent;
        draggedCard = null;
        originalParent = null;

        if (
          cardRect.left + cardRect.width / 2 > playedRect.left &&
          cardRect.left + cardRect.width / 2 < playedRect.right &&
          cardRect.top + cardRect.height / 2 > playedRect.top &&
          cardRect.top + cardRect.height / 2 < playedRect.bottom
        ) {
            if (currentOriginalParent) currentOriginalParent.appendChild(currentDraggedCard);
            currentDraggedCard.classList.remove('dragging');
            currentDraggedCard.style.position = '';
            currentDraggedCard.style.left = '';
            currentDraggedCard.style.top = '';
            currentDraggedCard.style.zIndex = '';

            playCard(playerId, cardIndex);

        } else {
            if (currentOriginalParent) currentOriginalParent.appendChild(currentDraggedCard);
            
            currentDraggedCard.classList.remove('dragging');
            currentDraggedCard.style.position = '';
            currentDraggedCard.style.left = '';
            currentDraggedCard.style.top = '';
            currentDraggedCard.style.zIndex = '';
        }
    };

    // Touch events for mobile
    cardEl.addEventListener('touchstart', (e) => {
        cardEl.dataset.isDragging = 'true';
        startDrag(e);
    });
    cardEl.addEventListener('touchmove', moveDrag);
    cardEl.addEventListener('touchend', () => {
        setTimeout(() => cardEl.dataset.isDragging = 'false', 10);
        endDrag();
    });

    // Mouse events for desktop
    cardEl.addEventListener('mousedown', (e) => {
        if (e.button !== 0) return;
        cardEl.dataset.isDragging = 'true';
        startDrag(e);

        const upHandler = () => {
            document.removeEventListener('mousemove', moveDrag);
            document.removeEventListener('mouseup', upHandler);
            cardEl.dataset.isDragging = 'false';
            endDrag();
        };

        document.addEventListener('mousemove', moveDrag);
        document.addEventListener('mouseup', upHandler);
    });
}

/* ---------- HAND UI (Minor change for packed status) ---------- */
function updateHandUI() {
  const slotIds = ['slot-bottom', 'slot-top', 'slot-left', 'slot-right'];

  for (let i = 0; i < 4; i++) {
    const ha = document.getElementById(`handArea-${i}`);
    if (ha) ha.innerHTML = '';
  }

  players.forEach((p, idx) => {
    const slotEl = document.getElementById(slotIds[idx]);
    const ha = document.getElementById(`handArea-${idx}`);
    if (!slotEl || !ha) return;

    ha.style.position = 'absolute';
    ha.style.display = 'flex';
    ha.style.gap = '6px';
    ha.style.zIndex = 1000;
    ha.style.display='none';
    
    // If player is packed, don't allow showing hand and visually indicate it
    slotEl.classList.toggle('packed', p.packed);

    slotEl.onclick = () => { 
        if(p.type === 'human' && !p.packed) { 
            ha.style.display = ha.style.display==='flex'?'none':'flex'; 
        }
    };

    if(p.type==='human' && !p.packed){ // Only show human hand if not packed
      p.hand.forEach((card,i)=>{
        const img = document.createElement('img');
        img.src = card.img;
        img.className = 'card-img';
        img.alt = `${card.rank}${card.suit}`;
        img.dataset.playerid = p.id;
        img.dataset.cardindex = i;
        img.dataset.isDragging = 'false';

        img.onclick = () => { 
            if(!betPlaced) return log('Please place your bet first!');
            if(img.dataset.isDragging === 'true') return; 
            if(!gameActive) return; 
            if(players[turnIndex].id!==p.id) return log('Not your turn!'); 
            playCard(p.id,i); 
        };
        
        setupCardDragAndDrop(img);
        
        ha.appendChild(img);
      });
    } else if (p.hand.length > 0) {
        // Show face-down cards for AI/other players if they still hold cards and are not packed (for visual)
        for (let i = 0; i < p.hand.length; i++) {
            const img = document.createElement('img');
            img.src = 'cards/back.png'; // Assuming you have a card back image
            img.className = 'card-img back';
            ha.appendChild(img);
        }
    }
  });
  updatePlayedArea();
  highlightTurn(turnIndex);
}

/* ---------- PLAY CARD (Minor Change) ---------- */
async function playCard(playerIdx, cardIdx){
  const p = players[playerIdx];

  if (!betPlaced) {
      if(p.type === 'human') log('Please place your bet before playing!');
      return;
  }
  if (p.packed) return log(`${p.name} cannot play, they are packed.`);
  
  if(!p || p.hand.length<=cardIdx) return;
  if(playedCards.find(x=>x.player.id===playerIdx)) return log('You have already played a card this round.');

  const card = p.hand.splice(cardIdx,1)[0];
  playedCards.push({player:p, card});

  const slotId = mapIndexToSlot(playerIdx,players.length);
  const slotEl = document.getElementById(slotId);
  const start = slotEl ? getElementCenter(slotEl) : {x:window.innerWidth/2, y:window.innerHeight/2};
  const tableEl = document.querySelector('.table');
  const center = getElementCenter(tableEl);
  const control = {x:(start.x+center.x)/2, y:Math.min(start.y,center.y)-160};

  const temp = document.createElement('img');
  temp.src=card.img;
  temp.className='card-img anim-card';
  temp.style.width='64px';
  temp.style.height='92px';
  temp.style.zIndex=9999;
  document.body.appendChild(temp);

  playSound('throw');
  await animateCardThrow(temp,start,control,center,460,true);
  temp.remove();

  updatePlayedArea();
  log(`${p.name} played ${card.rank}${card.suit}`);

  // Check if all *active* players have played
  const activePlayersCount = players.filter(p => !p.packed).length;
  if(playedCards.length===activePlayersCount){
    setTimeout(()=>endRound(),800);
    return;
  }

  // Move to the next player that is NOT packed
  let nextIndex = (turnIndex + 1) % players.length;
  while(players[nextIndex].packed && nextIndex !== turnIndex){
      nextIndex = (nextIndex + 1) % players.length;
  }
  turnIndex = nextIndex;

  highlightTurn(turnIndex);
  updateHandUI();

  const next = players[turnIndex];
  if(next.type==='ai'){
    setTimeout(()=>{
      // Check again if AI packed while waiting (e.g. if it ran out of money in placeBet)
      if (next.packed) {
          turnIndex = (turnIndex + 1) % players.length;
          highlightTurn(turnIndex);
          return;
      }
      const idx = chooseCardAI(next.hand,playedCards,next.difficulty);
      playCard(next.id,idx??0);
    },600);
  } else {
    // If it's the human's turn, re-enable betting controls (Bet/Pack/Show) for their action
    disableBettingControls(false); 
  }
}

/* ---------- ROUND LOGIC (Updated for Betting) ---------- */
function computeRoundWinner(){
  let best=playedCards[0];
  for(const p of playedCards){
    if(p.card.suit===best.card.suit){
      if(p.card.value>best.card.value) best=p;
    } else if(suitRank(p.card.suit)>suitRank(best.card.suit)){
      best=p;
    }
  }
  return best.player;
}

function endRound(){
  if(playedCards.length===0) return;
  const winner = computeRoundWinner();
  
  // Betting Payout Logic: Winner gets the entire pot
  const potValue = currentPot; // Use the global pot
  
  // Winner receives the pot
  winner.wallet += potValue; 
  winner.score++;
  
  log(`🏆 ${winner.name} wins Round ${roundNum}! Pot: ${potValue} points.`);
  updateScoreboard();
  highlightWinner(winner.id);

  // Call the cleanup helper
  showRoundPopup(`🏆 Round ${roundNum} Winner!`, `${winner.name} wins ${potValue} points`, sound.win, 1800, ()=>{
      endRoundCleanUp(winner);
  });
}

/* ---------- POPUP (No Change) ---------- */
function showRoundPopup(title,message,soundObj=null,duration=1500,callback=null){
  if(soundObj){
    soundObj.currentTime=0;
    soundObj.play().catch(()=>{});
  }
  const popup=document.createElement('div');
  popup.className='popup';
  popup.innerHTML=`<div class="popup-content"><h2>${title}</h2><p>${message}</p></div>`;
  document.body.appendChild(popup);
  requestAnimationFrame(()=>popup.classList.add('show'));
  setTimeout(()=>{
    popup.classList.remove('show');
    setTimeout(()=>{popup.remove(); if(callback)callback();},300);
  },duration);
}

/* ---------- UTILITIES (Minor Change) ---------- */
function suitRank(s){ return s==='♠'?4:s==='♥'?3:s==='♦'?2:1; }

function updatePlayedArea(){
  playedArea.innerHTML='';
  for(const p of playedCards){
    const slot=document.createElement('div'); slot.className='played-slot';
    const img=document.createElement('img'); img.src=p.card.img; img.alt=`${p.card.rank}${p.card.suit}`;
    slot.appendChild(img);
    const who=document.createElement('div'); who.style.marginTop='6px'; who.style.fontSize='12px';
    who.textContent=p.player.name;
    slot.appendChild(who);
    playedArea.appendChild(slot);
  }
}

function updateScoreboard(){
  // Updated scoreboard to show Wallet balance and Current Bet
  scoreboardDiv.innerHTML=players.map(p=>`<div class="score-entry ${p.packed?'packed':''}">
    ${p.name}: <b>${p.score}</b> (${p.hand.length}) | 💰 ${p.wallet} pts.
    ${p.currentBet > 0 ? ` (Bet: ${p.currentBet})` : ''}
    ${p.packed ? ' (PACKED)' : ''}
    </div>`).join('');
}

function highlightTurn(index){
  document.querySelectorAll('.player-slot').forEach(el=>el.classList.remove('active','winner'));
  const el=document.getElementById(mapIndexToSlot(index,players.length));
  if(el) el.classList.add('active');
}

function highlightWinner(id){
  const el=document.getElementById(mapIndexToSlot(id,players.length));
  if(el){ el.classList.add('winner'); setTimeout(()=>el.classList.remove('winner'),1400); }
}

function mapIndexToSlot(index,count){
  const base=['slot-bottom','slot-top','slot-left','slot-right'];
  return base[index%base.length];
}

//* ---------- GAME OVER (No Change) ---------- */
function showFinalResults(){
  const sorted = [...players].sort((a,b)=> b.wallet - a.wallet);

  log(`<hr><b>🏁 Game Over!</b> Wealthiest Player: <b>${sorted[0].name}</b> (Wallet: ${sorted[0].wallet})`);
  
  const gameOverSound = new Audio('sounds/gameover.mp3');
  gameOverSound.volume = 0.5;
  gameOverSound.currentTime = 0;
  gameOverSound.play().catch(()=>{});

  const popup = document.createElement('div');
  popup.className = 'popup';
  popup.innerHTML = `
    <div class="popup-content">
      <h2>🏁 Game Over!</h2>
      <p>Wealthiest Player: <b>${sorted[0].name}</b> (Wallet: ${sorted[0].wallet})</p>
      <button id="newGameBtn" class="btn primary">Play Again</button>
    </div>
  `;
  document.body.appendChild(popup);

  requestAnimationFrame(()=> popup.classList.add('show'));

  document.getElementById('newGameBtn').onclick = () => {
    popup.remove();
    startGame();
  };

  setTimeout(()=>{
    if(document.body.contains(popup)){
      popup.remove();
      startGame();
    }
  },5000);
}

/* ---------- AUTO PLAY ROUND (Minor Change) ---------- */
function autoPlayRound(){
  if(!gameActive) return;
  const rounds = players[0].hand.length;
  let delay = 0;
  for(let r=0; r<rounds; r++){
    setTimeout(()=>{
      // Simple bet implementation for auto play
      players.forEach(p => { 
            if(p.wallet > 0 && !p.packed) {
                const bet = 1;
                p.currentBet += bet; 
                p.wallet -= bet;
                currentPot += bet;
            } else if (p.wallet === 0) {
                p.packed = true;
            }
        });
      betPlaced = true;
      updateScoreboard();
      updatePotDisplay();

      for(let i=0; i<players.length; i++){
        setTimeout(()=>{ 
          if(players[i].hand.length && !players[i].packed) playCard(i,0); 
        }, i*400);
      }
    }, delay);
    delay += players.length*500 + 700;
  }
}

/* ---------- RESET GAME (No Change) ---------- */
function resetGame(){ 
  location.reload(); 
}

/* ---------- SLOT NAMES (No Change) ---------- */
function updateSlotNames(){
  const slotIds=['slot-bottom','slot-top','slot-left','slot-right'];
  players.forEach((p,i)=>{
    const el=document.getElementById(slotIds[i]);
    if(el) el.querySelector('.name').textContent=p.name;
  });
}

/* ---------- INIT (No Change) ---------- */
createConfigUI();
log('Ready — configure and click Start.');