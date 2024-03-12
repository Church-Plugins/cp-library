export default function formatDuration(value) {
	const minuteInSeconds = 60;
	const hourInSeconds = minuteInSeconds * 60;

  const hour = Math.floor(value / hourInSeconds );
  value = value - ( hour * hourInSeconds );

  const minute = Math.floor(value / minuteInSeconds);
  value = value - ( minute * minuteInSeconds );

  const secondLeft = Math.floor(value);

  let hourDisplay   = hour < 10 ? `0${hour}` : hour;
  let minuteDisplay = minute < 10 ? `0${minute}` : minute;
  let secondDisplay = secondLeft < 10 ? `0${secondLeft}` : secondLeft;

  secondDisplay = secondLeft > 59 ? 59 : secondDisplay;

  return hour ? `${hourDisplay}:${minuteDisplay}:${secondDisplay}` : `${minuteDisplay}:${secondDisplay}`;
}
