export default function formatDuration(value) {
  const minute = Math.floor(value / 60);
  const secondLeft = Math.round(value - minute * 60);
  const minuteDisplay = minute < 9 ? `0${minute}` : minute;
  const secondDisplay = secondLeft < 9 ? `0${secondLeft}` : secondLeft;
  return `${minuteDisplay}:${secondDisplay}`;
}
