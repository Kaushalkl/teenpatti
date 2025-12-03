// js/game.js
let wallet = Number(INITIAL_WALLET || 0);
let pot = 0;
let lastBet = 10;
let round = 0;
let players = [];
let deck = [];
const MIN_BET = 10;

const walletEl = document.getElementById('walletDisplay');
const potEl = document.getElementById('currentPot');
const roundEl = document.getElementById('roundNo');

const startBtn = document.getElementById('startBtn');
const buyinInput = document.getElementById('buyin');

const blindBtn = document.getElementById('blindBtn');
const chaalBtn = document.getElementById('chaalBtn');
const packBtn = document.getElementById('packBtn');
const showBtn = document.getElementById('showBtn');

function updateUI(){
  walletEl.textContent = wallet.toFixed(2);
  potEl.textContent = '₹' + pot.toFixed(2);
  roundEl.textContent = round;
}

function delay(ms){ return new Promise(r=>setTimeout(r,ms)); }

const SUITS=['♠','♥','♦','♣'], RANKS=['2','3','4','5','6','7','8','9','10','J','Q','K','A'];
function createDeck(){ const d=[]; for(const s of SUITS) for(const r of RANKS) d.push({suit:s,rank:r}); return d; }
function shuffle(d){ for(let i=d.length-1;i>0;i--){ const j=Math.floor(Math.random()*(i+1)); [d[i],d[j]]=[d[j],d[i]]; } return d; }

function setupPlayers(buyin){
  players = [
    {id:0,name:'You',isHuman:true,stack:buyin,hand:[],folded:false,active:true},
    {id:1,name:'AI1',isHuman:false,stack:1000,hand:[],folded:false,active:true},
    {id:2,name:'AI2',isHuman:false,stack:1000,hand:[],folded:false,active:true},
    {id:3,name:'AI3',isHuman:false,stack:1000,hand:[],folded:false,active:true},
  ];
  renderHands();
}

function dealCards(){
  deck = shuffle(createDeck());
  for(let p of players){
    p.hand = [deck.pop(),deck.pop(),deck.pop()];
    p.folded = false;
    p.active = true;
  }
  renderHands();
}

function renderHands(){
  for(let i=0;i<4;i++){
    const el = document.getElementById('handArea-'+i);
    el.innerHTML = '';
    const p = players[i];
    if(!p || !p.active) continue;
    if(p.isHuman){
      p.hand.forEach(c=>{
        const d = document.createElement('div');
        d.textContent = c.rank + c.suit;
        d.style.display='inline-block';
        d.style.margin='0 6px';
        d.style.padding='6px';
        d.style.border='1px solid #ddd';
        el.appendChild(d);
      });
    } else {
      // show back for AI (3 cards)
      for(let k=0;k<3;k++){
        const b = document.createElement('div');
        b.textContent = '🂠';
        b.style.display='inline-block';
        b.style.margin='0 6px';
        b.style.padding='6px';
        el.appendChild(b);
      }
    }
    const label = document.createElement('div');
    label.textContent = p.name + ' • ₹' + p.stack.toFixed(2);
    el.appendChild(document.createElement('br'));
    el.appendChild(label);
  }
}

async function aiPlayFrom(idx){
  for(let i=idx;i<players.length;i++){
    const p = players[i];
    if(!p || p.folded || !p.active || p.isHuman) continue;
    await delay(600 + Math.random()*700);
    const r = Math.random();
    if(r < 0.25){
      p.folded = true; p.active = false;
      console.log(p.name + ' folded');
    } else {
      const amt = lastBet || MIN_BET;
      if(p.stack >= amt){
        p.stack -= amt;
        pot += amt;
        console.log(p.name + ' chaal ' + amt);
      } else {
        p.folded = true; p.active = false;
      }
    }
    updateUI();
    renderHands();
    // if only one left award
    const active = players.filter(p=>p.active && !p.folded);
    if(active.length <= 1){
      awardPot();
      return;
    }
  }
  // enable human controls if still active
  if(players[0].active && !players[0].folded) enableHuman(true);
}

function enableHuman(state){
  blindBtn.disabled = !state;
  chaalBtn.disabled = !state;
  packBtn.disabled = !state;
  showBtn.disabled = !state;
}

startBtn.addEventListener('click', async ()=>{
  const buyin = Number(buyinInput.value) || 100;
  if(wallet < buyin){
    alert('Insufficient wallet for buy-in');
    return;
  }
  // deduct buyin from wallet & sync server
  wallet = Number((wallet - buyin).toFixed(2));
  updateUI();
  await syncWalletToServer(wallet);

  setupPlayers(buyin);
  dealCards();
  pot = 0; lastBet = MIN_BET; round++;
  updateUI();
  enableHuman(true);
});

blindBtn.addEventListener('click', async ()=>{
  const amt = MIN_BET;
  if(wallet < amt){ alert('Insufficient wallet'); return; }
  wallet = Number((wallet - amt).toFixed(2));
  players[0].stack -= amt;
  pot += amt;
  lastBet = amt;
  updateUI(); renderHands();
  await syncWalletToServer(wallet);
  enableHuman(false);
  aiPlayFrom(1);
});

chaalBtn.addEventListener('click', async ()=>{
  let amt = Number(prompt('Enter Chaal amount', lastBet || MIN_BET)) || lastBet || MIN_BET;
  if(amt < MIN_BET){ alert('Min bet ' + MIN_BET); return; }
  if(wallet < amt){ alert('Insufficient wallet'); return; }
  wallet = Number((wallet - amt).toFixed(2));
  players[0].stack -= amt;
  pot += amt;
  lastBet = amt;
  updateUI(); renderHands();
  await syncWalletToServer(wallet);
  enableHuman(false);
  aiPlayFrom(1);
});

packBtn.addEventListener('click', async ()=>{
  players[0].folded = true; players[0].active = false;
  enableHuman(false);
  await aiPlayFrom(1);
});

showBtn.addEventListener('click', async ()=>{
  // showdown among active players
  await resolveShow();
});

function rankHand(hand){
  const idx = r => RANKS.indexOf(r);
  const ranks = hand.map(c=>c.rank).sort((a,b)=>idx(a)-idx(b));
  const suits = hand.map(c=>c.suit);
  const indices = ranks.map(idx);
  const isSeq = (indices[2]-indices[0]===2 && indices[0]+1===indices[1]);
  const isFlush = suits.every(s=>s===suits[0]);
  const counts = {};
  ranks.forEach(r=>counts[r]=(counts[r]||0)+1);
  const vals = Object.values(counts).sort((a,b)=>b-a);
  let tier=0, tie=0;
  if(vals[0]===3){ tier=6; tie=RANKS.indexOf(Object.keys(counts).find(k=>counts[k]===3)); }
  else if(isSeq && isFlush){ tier=5; tie=Math.max(...indices); }
  else if(isSeq){ tier=4; tie=Math.max(...indices); }
  else if(isFlush){ tier=3; tie=Math.max(...indices); }
  else if(vals[0]===2){ tier=2; const pair=Object.keys(counts).find(k=>counts[k]===2); const kicker=Object.keys(counts).find(k=>counts[k]===1); tie=RANKS.indexOf(pair)*100+RANKS.indexOf(kicker);}
  else { tier=1; tie=indices.slice().sort((a,b)=>b-a).reduce((acc,v)=>acc*100+v,0); }
  return tier*100000 + tie;
}

async function resolveShow(){
  const contenders = players.filter(p=>p.active && !p.folded);
  if(contenders.length <= 1){ awardPot(); return; }
  let best=-Infinity, bestPlayers=[];
  contenders.forEach(p=>{
    const score = rankHand(p.hand);
    p._score = score;
    if(score > best){ best = score; bestPlayers = [p]; }
    else if(score === best) bestPlayers.push(p);
  });

  if(bestPlayers.length === 1){
    const w = bestPlayers[0]; w.stack += pot;
    if(w.isHuman){ wallet = Number((wallet + pot).toFixed(2)); await syncWalletToServer(wallet); }
    console.log(w.name + ' wins ' + pot);
  } else {
    const share = Number((pot / bestPlayers.length).toFixed(2));
    bestPlayers.forEach(w=>{
      w.stack += share;
      if(w.isHuman) wallet = Number((wallet + share).toFixed(2));
    });
    if(bestPlayers.some(w=>w.isHuman)) await syncWalletToServer(wallet);
    console.log('Tie: ' + bestPlayers.map(p=>p.name).join(', '));
  }
  pot = 0; updateUI(); renderHands(); enableHuman(false);
}

function awardPot(){
  const left = players.filter(p=>p.active && !p.folded);
  if(left.length === 1){
    const w = left[0]; w.stack += pot;
    if(w.isHuman){ wallet = Number((wallet + pot).toFixed(2)); syncWalletToServer(wallet); }
    console.log(w.name + ' awarded pot ' + pot);
  }
  pot = 0; updateUI(); renderHands();
}

async function syncWalletToServer(newWallet){
  try {
    await fetch('update_wallet.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({wallet:newWallet})
    });
  } catch(e){
    console.warn('sync failed', e);
  }
}

updateUI();
