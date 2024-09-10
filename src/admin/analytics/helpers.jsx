


export function parseTime(seconds) {
  seconds = parseInt(seconds)
  if(isNaN(seconds)) {
    return '0:00'
  }
  const isHour = seconds >= 60 * 60
  const date = new Date(0);
  date.setSeconds(seconds);
  const timeString = date.toISOString().substring(isHour ? 11 : 14, 19)
  return timeString
}

export function validNumber( num, float = false, fallback = 0 ) {
  num = (float ? parseFloat : parseInt)(num)

  if(isNaN(num)) {
    return fallback
  }

  return num
}