{{-- --------------------------------------------------------------
     Blade: backend/draw/preview.blade.php  (excerpt)
     Only PHP vars you must pass to this view are now:
       $fixtureMap   associative array keyed "bracketId-matchNr"
       $isDrawLocked boolean
       $draw         the Draw model (if you still need it)
   -------------------------------------------------------------- --}}
   <div id="draw-wrapper">
<div id="draw-container"></div>
   </div>

<h3>All Playoff Fixtures</h3>
<script>
   window.fixtureMap = @json($fixtureMap);
window.isDrawLocked = @json($isDrawLocked);

    /* ---------- bracket IDs ---------- */
    const bracketIds = [...new Set(
        Object.keys(window.fixtureMap)
        .map(k => parseInt(k.split('-')[0], 10))
    )].filter(Number.isFinite).sort((a, b) => a - b);

    /* ---------- helper functions (unchanged) ---------- */
    function generateMatch(x, y, h = 60, key = '') {
        const f = window.fixtureMap[key] ?? {};
        const n1 = f.p1 === 0 ? 'Bye' : (f.p1 || '');
        const n2 = f.p2 === 0 ? 'Bye' : (f.p2 || '');
        const id = f.id ?? '';

        return `
          <g>
            <line x1="${x}" y1="${y+20}"    x2="${x+220}" y2="${y+20}"  stroke="black"/>
            <line x1="${x}" y1="${y+h}"     x2="${x+220}" y2="${y+h}"   stroke="black"/>
            <line x1="${x+220}" y1="${y+20}" x2="${x+220}" y2="${y+h}"  stroke="black"/>
            <text x="${x+10}" y="${y+15}"        font-weight="bold">${n1}</text>
            <text x="${x+10}" y="${y+h-5}"       font-weight="bold">${n2}</text>
            <text x="${x+110}" y="${y+h/2}" text-anchor="middle" fill="#999">#${id}</text>
          </g>`;
    }

    function buildSingleDraw(yOffset = 0, bracketId = 1) {
        const qHeight = 90,
            sHeight = 120,
            fHeight = 240;
        const conHeight = 90,
            conFinalHeight = conHeight * 2;

        // Coordinate helpers
        const quarterY = [40, 160, 280, 400].map(y => y + yOffset);
        const semiY = [
            (quarterY[0] + qHeight / 2 + quarterY[1] + qHeight / 2) / 2 - sHeight / 2,
            (quarterY[2] + qHeight / 2 + quarterY[3] + qHeight / 2) / 2 - sHeight / 2
        ];
        const finalY = (semiY[0] + sHeight / 2 + semiY[1] + sHeight / 2) / 2 - fHeight / 2;
        const finalX = 230 + 220;

        // Label
        const drawLabel = `Draw ${(bracketId - 1) * 8 + 1}–${bracketId * 8}`;
        let svg = `<text x="10" y="${yOffset + 20}" font-size="18" font-weight="bold">${drawLabel}</text>`;

        // Quarter-finals (#1–4)
        for (let i = 0; i < 4; i++) {
            svg += generateMatch(10, quarterY[i], qHeight, `${bracketId}-${i+1}`);
        }
        // Semi-finals (#5–6)
        svg += generateMatch(230, semiY[0], sHeight, `${bracketId}-5`);
        svg += generateMatch(230, semiY[1], sHeight, `${bracketId}-6`);
        // Final (#7)
        svg += generateMatch(finalX, finalY, fHeight, `${bracketId}-7`);
        svg +=
            `<line x1="${finalX+220}" y1="${finalY+fHeight/2}" x2="${finalX+320}" y2="${finalY+fHeight/2}" stroke="black" />`;

        // 3rd/4th playoff (#12)
        const thirdPlaceY = finalY + fHeight + 40;
        svg += generateMatch(finalX + 300, thirdPlaceY, conHeight, `${bracketId}-12`);
        svg +=
            `<line x1="${finalX+520}" y1="${thirdPlaceY+conHeight/2}" x2="${finalX+620}" y2="${thirdPlaceY+conHeight/2}" stroke="black" />`;

        // Consolation bracket (SF #8-9, Final #10, 7/8 playoff #11)
        const conOffsetY = yOffset + 550;
        const conSemiY = [conOffsetY, conOffsetY + 150];
        const conFinalY = (conSemiY[0] + conHeight / 2 + conSemiY[1] + conHeight / 2) / 2 - conFinalHeight / 2;
        const conFinalX = 230;
        const con78Y = conFinalY + conFinalHeight + 40;

        svg += generateMatch(10, conSemiY[0], conHeight, `${bracketId}-8`);
        svg += generateMatch(10, conSemiY[1], conHeight, `${bracketId}-9`);
        svg += generateMatch(conFinalX, conFinalY, conFinalHeight, `${bracketId}-10`);
        svg +=
            `<line x1="${conFinalX+220}" y1="${conFinalY+conFinalHeight/2}" x2="${conFinalX+320}" y2="${conFinalY+conFinalHeight/2}" stroke="black" />`;

        svg += generateMatch(conFinalX + 300, con78Y, conHeight, `${bracketId}-11`);
        svg +=
            `<line x1="${conFinalX+520}" y1="${con78Y+conHeight/2}" x2="${conFinalX+620}" y2="${con78Y+conHeight/2}" stroke="black" />`;

        return svg;
    }

    // -----------------------------------------------------------------
    // 4️⃣  Build *all* draws
    // -----------------------------------------------------------------
    function buildAllDraws() {
        if (!bracketIds.length) {
            console.error("fixtureMap is empty – nothing to draw");
            return;
        }

        let svg = `<svg width="1800" height="${bracketIds.length * 1000}" xmlns="http://www.w3.org/2000/svg">`;

        bracketIds.forEach((bracketId, index) => {
            const yOffset = index * 1000;
            svg += buildSingleDraw(yOffset, bracketId);
        });

        svg += '</svg>';
        document.getElementById('draw-container').innerHTML = svg;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const container = document.getElementById('draw-container');
        if (!container) {
            console.error('❌ draw-container element not found in DOM');
            return;
        }
        buildAllDraws();
    });
</script>
