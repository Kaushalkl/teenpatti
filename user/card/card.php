<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Tash patti </title>
  <link rel="stylesheet" href="style.css" />
  <style>
/* ===== MODERN TEEN PATTI TABLE ===== */
/* ===== TABLE ===== */
.table {
  position: relative;
  flex: 1;
  height: 100px;
  border-radius: 50%;
  background: radial-gradient(circle at center, var(--felt-mid) 0%, var(--felt-dark) 100%);
  box-shadow: inset 0 0 60px rgba(0,0,0,0.6);
}
.table-wrap {
    position: relative;
    width: 100%;
    height: 80vh;
    display: flex;
    justify-content: center;
    align-items: center;
    perspective: 1200px;
    /* Optional: Slight rotation for 3D effect */
    transform: rotateX(10deg); 
}

/* Table base */
.table {
    position: relative;
    width: 60vh; /* Use vh for a perfect circle on all screen sizes */
    height: 60vh;
    max-width: 800px; /* Cap size for very large screens */
    max-height: 800px;
    border-radius: 50%;
    /* Red Felt Gradient */
    background: radial-gradient(circle at 50% 30%, #ff3b3b 0%, #a81414 60%, #610808 100%);
    
    /* ADDED: Outer boundary for the table */
    border: 20px solid #098b0d; /* Gold/Yellow border */
    /* You can adjust the color and thickness */

    box-shadow: 
        inset 0 8px 25px rgba(255,255,255,0.05),
        inset 0 -8px 30px rgba(0,0,0,0.4),
        /* Adjusted outer shadow to complement the border */
        0 0 0 10px rgba(255, 204, 0, 0.2), /* A subtle glow around the border */
        0 10px 50px rgba(0,0,0,0.6);
    display: flex;
    justify-content: center;
    align-items: center;
    transition: all 0.4s ease;
    overflow: hidden;
}

/* Felt texture overlay */
.table::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50%;
    /* Subtle felt texture */
    background: repeating-radial-gradient(circle at center, rgba(255,255,255,0.02) 0 2px, transparent 2px 6px);
    pointer-events: none;
    mix-blend-mode: overlay;
}

/* Center card glow */
.table::after {
    content: '';
    position: absolute;
    /* Inset 25% from the edge */
    inset: 25%; 
    border-radius: 50%;
    /* Yellow/Orange glow */
    background: radial-gradient(circle, rgba(255,203,116,0.15) 0%, transparent 70%);
    pointer-events: none;
    z-index: 1;
}

/* ===== LEFT SIDE ANIMATED GIRL / DEALER ===== */
.table .dealer {
    position: absolute;
    /* Position adjusted for better placement on a large circular table */
    left: 10%; 
    top: 50%;
    transform: translateY(-50%); /* Center vertically relative to its position */

    width: 120px;
    height: 220px;
    /* IMPORTANT: Replace 'images/girl.png' with the actual path to your saved image */
    background: url('images/girl.png') no-repeat center/cover;
    border-radius: 12px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.6), 0 0 12px rgba(255,203,116,0.4);
    animation: floatDealer 2.5s ease-in-out infinite alternate;
    z-index: 10;
    cursor: pointer;
}

/* Floating animation for realism */
@keyframes floatDealer {
    0% { transform: translateY(-50%) rotateZ(-1deg); } /* Keep center alignment */
    50% { transform: translateY(-58%) rotateZ(1deg); } /* Lift up 8px from center (-50% + -8px) */
    100% { transform: translateY(-50%) rotateZ(-1deg); } /* Back to center alignment */
}

/* Optional glow when hovering */
.table .dealer:hover {
    box-shadow: 0 20px 40px rgba(0,0,0,0.7), 0 0 18px rgba(255,203,116,0.6);
    /* Lift up and scale slightly */
    transform: translateY(-55%) rotateZ(2deg) scale(1.02); 
    transition: all 0.3s ease;
}
/* ===== PLAYER HANDS (Unified Scrollable) ===== */
.player-hand {
  display: flex;
  align-items: center;
  justify-content: flex-start; /* cards align from start */
  gap: 8px;
  padding: 10px;
  border-radius: 16px;
  background: rgba(255, 255, 255, 0.05);
  box-shadow: 0 0 15px rgba(0, 255, 150, 0.3);
  transition: 0.3s ease;
  scrollbar-color: #00ff99 rgba(255, 255, 255, 0.05);
}

/* === BOTTOM PLAYER === */
#handArea-0 {
  position: absolute;
  bottom: 50px;
  left: 50%;
  transform: translateX(-50%);
  width: 70%;
  overflow-x: auto;
  overflow-y: hidden;
}

/* === TOP PLAYER === */
#handArea-1 {
  position: absolute;
  top: 50px;
  left: 50%;
  transform: translateX(-50%);
  width: 70%;
  overflow-x: auto;
  overflow-y: hidden;
}

/* === LEFT & RIGHT PLAYERS — same slider style as top/bottom === */

/* === LEFT PLAYER === */
#handArea-2 {
  position: absolute;
  left: 50px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  flex-direction: column;      /* vertical stack */
  justify-content: center;
  align-items: center;
  height: 70%;                 /* vertical scroll area */
  overflow-y: auto;            /* vertical scrollbar */
  overflow-x: hidden;
  padding: 4px;
  border-radius: 4px;
  background: rgba(255,255,255,0.08);
  box-shadow: 0 0 15px rgba(0,255,170,0.3);
}

/* === RIGHT PLAYER === */
#handArea-3 {
  position: absolute;
  right: 50px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  flex-direction: column;      /* vertical stack */
  justify-content: center;
  align-items: center;
  height: 70%;
  overflow-y: auto;            /* vertical scrollbar */
  overflow-x: hidden;
  padding: 4px;
  border-radius: 4px;
  background: rgba(255,255,255,0.08);
  box-shadow: 0 0 15px rgba(0,255,170,0.3);
}

/* Rotate cards sideways for left/right edges and add extra spacing */
#handArea-2 .card-img {
  transform: rotate(-90deg);   /* left player cards rotated CCW */
  margin: -18px 25px;               /* vertical spacing between cards increased */
}

#handArea-3 .card-img {
  transform: rotate(90deg);    /* right player cards rotated CW */
  margin:-18px 25px;               /* vertical spacing between cards increased */
}

/* === Vertical scrollbars for left/right players === */
#handArea-2::-webkit-scrollbar,
#handArea-3::-webkit-scrollbar {
  width: 200px;                 /* vertical scrollbar width */
}

#handArea-2::-webkit-scrollbar-thumb,
#handArea-3::-webkit-scrollbar-thumb {
  background: linear-gradient(45deg, #00ffcc, #00cc88);
  border-radius: 20px;
  box-shadow: 0 0 6px #00ffcc;
}

#handArea-2::-webkit-scrollbar-track,
#handArea-3::-webkit-scrollbar-track {
  background: rgba(255,255,255,0.05);
  border-radius: 20px;
}
    .scoreboard, .log {
      background: rgba(255,255,255,0.08);
      border-radius: 10px;
      padding: 10px;
      margin-bottom: 15px;
    }
    .scoreboard h3, .log h3 {
      margin: 0 0 8px;
      text-align: center;
      color: #00ffcc;
    }
  </style>
</head>
<body>
  <div id="app">
    <header class="topbar">
      <div class="brand">
        <div class="title">Tash Patti</div>
      </div>

      <div class="controls">
        <label>Players
          <select id="playerCount">
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4" selected>4</option>
          </select>
        </label>

        <label>AI level
          <select id="aiLevel">
            <option value="easy">Easy</option>
            <option value="medium" selected>Medium</option>
            <option value="hard">Hard</option>
          </select>
        </label>

        <button id="configureBtn" class="btn">⚙️ Configure</button>
        <button id="startBtn" class="btn primary">▶ Start</button>
        <button id="dealBtn" class="btn">🎴 Deal</button>
        <button id="autoPlayBtn" class="btn">🤖 Auto</button>
        <button id="resetBtn" class="btn danger">⟳ Reset</button>
      </div>
    </header>

    <main class="main-wrap">
      <section class="table-wrap">
        <div class="table" id="table">
    <!-- dealer character -->
    <div class="dealer"></div>
    <!-- dealer hand highlight -->
    <div class="dealer-hand"></div>

    <!-- played area (center) -->
    <div class="played-area" id="played-area">
        <!-- cards will appear here dynamically -->
    </div>
</div>


          <!-- Player Slots -->
<div id="slot-top" class="player-slot top">
  <img class="player-icon" src="images/player1.png" alt="Player 1" />
  <div class="name">Player</div>
  <div class="stack"></div>
</div>

<div id="slot-left" class="player-slot left">
  <img class="player-icon" src="images/player2.png" alt="Player 2" />
  <div class="name">Player</div>
  <div class="stack"></div>
</div>

<div id="slot-right" class="player-slot right">
  <img class="player-icon" src="images/player3.png" alt="Player 3" />
  <div class="name">Player</div>
  <div class="stack"></div>
</div>

<div id="slot-bottom" class="player-slot bottom">
  <img class="player-icon" src="images/player4.png" alt="Player 4" />
  <div class="name">Player</div>
  <div class="stack"></div>
</div>

          <!-- Center Area -->
          <div class="center-area">
            <div id="playedArea" class="played-area"></div>
            <div id="roundInfo" class="round-info">Round: 0</div>
          </div>

          <!-- Player Hands -->
          <div id="handArea-0" class="player-hand"></div>
          <div id="handArea-1" class="player-hand"></div>
          <div id="handArea-2" class="player-hand"></div>
          <div id="handArea-3" class="player-hand"></div>

        </div>
      </section>

      <!-- PANEL -->
      <aside class="panel">
        <div class="scoreboard">
          <h3>Scoreboard</h3>
          <div id="scoreboard"></div>
        </div>

        <div class="log">
          <h3>Game Log</h3>
          <div id="log"></div>
        </div>
      </aside>
    </main>

    <footer class="footer">
    </footer>
  </div>

  <script type="module" src="ai.js"></script>
  <script type="module" src="game.js"></script>

  <script>
    function updateSlotNames(players) {
      const slots = ['slot-bottom','slot-top','slot-left','slot-right'];
      players.forEach((p,i)=>{
        const el = document.getElementById(slots[i]);
        if(el) el.querySelector('.name').textContent = p.name;
      });
    }

    document.getElementById('startBtn').addEventListener('click', ()=>{
      const playerCount = parseInt(document.getElementById('playerCount').value);
      const aiLevel = document.getElementById('aiLevel').value;
      const players = [];

      for(let i=0;i<playerCount;i++){
        players.push({name: `Player ${i+1}`, type: 'human'});
      }
      for(let i=playerCount;i<4;i++){
        players.push({name: `AI ${i}`, type: aiLevel});
      }

      updateSlotNames(players);
      const slotIds = ['slot-bottom','slot-top','slot-left','slot-right'];
      slotIds.forEach((id, idx)=>{
        const el = document.getElementById(id);
        if(idx>=players.length) el.style.display='none';
        else el.style.display='flex';
      });
    });
  </script>
</body>
</html>
