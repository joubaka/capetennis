function toggleButtonPublished(draw,button,texton,textoff){
  
    if (draw.published == 1) {
        button.removeClass('btn-danger').addClass('btn-success').text(texton)
      } else {
        button.removeClass('btn-success').addClass('btn-danger').text(textoff)

      }
}

function toggleButtonPublishedSchedule(draw,button,texton,textoff){
  
  if (draw.oop_published == 1) {
      button.removeClass('btn-danger').addClass('btn-success').text(texton)
    } else {
      button.removeClass('btn-success').addClass('btn-danger').text(textoff)

    }
}