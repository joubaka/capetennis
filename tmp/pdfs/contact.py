from PIL import Image, ImageOps, ImageDraw
from pathlib import Path
files=sorted(Path('tmp/pdfs').glob('event252-*.png'))
for start in range(0,len(files),6):
    sheet=Image.new('RGB',(1080,1580),'#dddddd')
    for i,f in enumerate(files[start:start+6]):
        im=Image.open(f); im.thumbnail((530,750))
        x=(i%2)*540; y=(i//2)*525
        im.thumbnail((350,495))
        sheet.paste(im,(x+80,y+20)); ImageDraw.Draw(sheet).text((x+5,y+5),f.stem,fill='black')
    sheet.save(f'tmp/pdfs/contact-{start//6+1}.png')
