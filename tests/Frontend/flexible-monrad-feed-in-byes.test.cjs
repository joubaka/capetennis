const test = require('node:test');
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {withFeedInByes, withMainDrawByes, layout} = require('../../public/js/tennis-bracket-layout.js');

function fixture(size, byes) {
  const code = `require 'vendor/autoload.php'; $size=${size}; $byes=json_decode('${JSON.stringify(byes)}',true); $slots=[];
    for($i=0;$i<$size;$i++){ $p=strtr(str_pad(decbin($i),(int)log($size,2),'0',STR_PAD_LEFT),['0'=>'a','1'=>'b']);
      $slots[$p]=in_array($i,$byes,true)?['type'=>'bye']:['type'=>'player','id'=>$i+1]; }
    $draft=['size'=>$size,'mode'=>'feed_in_monrad','slots'=>$slots];
    $g=(new App\\Services\\Draw\\FlexibleMonradCompiler)->compile($draft); $matches=[]; $i=0;
    foreach($g['nodes'] as $key=>$node) $matches[$key]=$node+['number'=>++$i,'players'=>[null,null],'winner'=>null,'sets'=>[]];
    echo json_encode(['draft'=>$draft,'matches'=>$matches,'pdf'=>App\\Services\\Draw\\FlexibleMonradFeedInDisplay::expand($matches,$draft)]);`;
  return JSON.parse(execFileSync('php',['-r',code], {cwd:process.cwd(),encoding:'utf8'}));
}
test('16/32 feed-in byes preserve fixtures and match the PDF projection including sparse branches',()=>{
  for(const size of [16,32]) for(const byes of [[0],[0,1,2,3,8,9],[]]) {
    const state=fixture(size,byes), before=JSON.stringify(state.matches);
    const display=withFeedInByes(state.matches,state.draft);
    assert.deepEqual(display,state.pdf);
    assert.equal(JSON.stringify(state.matches),before);
    for(const [key,match] of Object.entries(state.matches)) assert.equal(display[key].number,match.number);
    if(byes.length) {
      const card=display.display_feed_bye_1_0;
      assert.equal(card.displayOnly,true);
      assert.equal(card.number,undefined);
      assert.equal(card.id,undefined);
      assert.deepEqual(card.players,[null,null]);
      const combined=withFeedInByes(withMainDrawByes(state.matches,state.draft),state.draft);
      assert.equal(combined.display_feed_bye_1_0.displayOnly,true);
      const feed=Object.entries(display).filter(([,m])=>m.section===card.section);
      const positions=layout(feed.map(([key,m])=>({key,column:m.round-1,sources:m.sources}))).positions;
      for(const [key,m] of feed) for(const source of m.sources) if(source.match=== 'display_feed_bye_1_0')
        assert.ok(positions.get(key).column>positions.get(source.match).column);
    } else assert.ok(Object.values(display).every(m=>!m.displayOnly));
  }
});
test('known loser advances through display bye while unresolved loser stays pending; incompatible graphs stay intact',()=>{
  const {matches,draft}=fixture(16,[0]);
  assert.equal(withFeedInByes(matches,draft).display_feed_bye_1_0.winner,null);
  matches.main_aab.players=[3,4]; matches.main_aab.winner=3;
  assert.deepEqual(withFeedInByes(matches,draft).display_feed_bye_1_0.players,[null,4]);
  const key=Object.keys(matches).find(key=>matches[key].section.endsWith(' · Feed-in'));
  matches[key].sources=[{type:'player',id:99},{type:'player',id:100}];
  assert.equal(withFeedInByes(matches,draft),matches);
});
