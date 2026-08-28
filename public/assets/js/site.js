const formatAudioTime = (seconds) => {
  if (!Number.isFinite(seconds) || seconds < 0) return '00:00';
  const total = Math.floor(seconds);
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const secs = total % 60;
  return hours > 0
    ? `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
    : `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
};

document.querySelectorAll('[data-podcast-player]').forEach((root) => {
  const audio = root.querySelector('[data-audio-engine]');
  const waveform = root.querySelector('[data-waveform]');
  const bars = root.querySelector('[data-waveform-bars]');
  const playhead = root.querySelector('[data-waveform-playhead]');
  const waveformSeek = root.querySelector('[data-waveform-seek]');
  const play = root.querySelector('[data-play]');
  const playIcon = root.querySelector('[data-play-icon]');
  const current = root.querySelector('[data-current-time]');
  const duration = root.querySelector('[data-duration]');
  const progressLine = root.querySelector('[data-progress-line]');
  const rate = root.querySelector('[data-rate]');
  const volume = root.querySelector('[data-volume]');
  if (!audio || !waveform || !bars || !play) return;

  let seed = Array.from(root.dataset.waveformSeed || 'isnomcms').reduce((value, char) => ((value * 31) + char.charCodeAt(0)) >>> 0, 2166136261);
  const random = () => {
    seed = (seed * 1664525 + 1013904223) >>> 0;
    return seed / 4294967296;
  };
  const supplied = (() => {
    try { return JSON.parse(root.dataset.waveform || '[]'); } catch { return []; }
  })();
  const amplitudes = supplied.length > 20 ? supplied : Array.from({ length: 112 }, (_, index) => {
    const envelope = Math.sin((index / 111) * Math.PI) * 0.55 + 0.2;
    return Math.min(1, 0.12 + envelope * (0.35 + random() * 0.65));
  });
  const fragment = document.createDocumentFragment();
  amplitudes.forEach((amplitude) => {
    const bar = document.createElement('i');
    bar.style.setProperty('--amplitude', Math.max(0.08, Math.min(1, Number(amplitude) || 0.08)));
    fragment.append(bar);
  });
  bars.replaceChildren(fragment);

  const update = () => {
    const ratio = audio.duration ? Math.min(1, audio.currentTime / audio.duration) : 0;
    root.style.setProperty('--audio-progress', `${ratio * 100}%`);
    playhead.style.left = `${ratio * 100}%`;
    progressLine.style.width = `${ratio * 100}%`;
    if (waveformSeek && document.activeElement !== waveformSeek) waveformSeek.value = String(Math.round(ratio * 1000));
    current.textContent = formatAudioTime(audio.currentTime);
    const playedBars = Math.round(amplitudes.length * ratio);
    bars.querySelectorAll('i').forEach((bar, index) => bar.classList.toggle('is-played', index < playedBars));
  };
  const updateState = () => {
    const paused = audio.paused;
    play.setAttribute('aria-label', paused ? 'Reproducir' : 'Pausar');
    play.setAttribute('aria-pressed', String(!paused));
    playIcon.textContent = paused ? '▶' : 'Ⅱ';
  };
  waveformSeek?.addEventListener('input', () => {
    if (!Number.isFinite(audio.duration)) return;
    audio.currentTime = (Number(waveformSeek.value) / 1000) * audio.duration;
    update();
  });
  play.addEventListener('click', () => { if (audio.paused) audio.play().catch(() => {}); else audio.pause(); });
  root.querySelectorAll('[data-skip]').forEach((button) => button.addEventListener('click', () => {
    audio.currentTime = Math.max(0, Math.min(audio.duration || Infinity, audio.currentTime + Number(button.dataset.skip)));
  }));
  rate?.addEventListener('change', () => { audio.playbackRate = Number(rate.value); });
  volume?.addEventListener('input', () => { audio.volume = Number(volume.value); });
  audio.addEventListener('loadedmetadata', () => { duration.textContent = formatAudioTime(audio.duration); update(); });
  audio.addEventListener('durationchange', update);
  audio.addEventListener('timeupdate', update);
  audio.addEventListener('play', updateState);
  audio.addEventListener('pause', updateState);
  audio.addEventListener('ended', updateState);
  updateState();
});

document.querySelectorAll('[data-copy-share]').forEach((button) => button.addEventListener('click', async () => {
  const status = button.closest('.episode-share')?.querySelector('[data-share-status]');
  try {
    await navigator.clipboard.writeText(button.dataset.copyShare);
    if (status) status.textContent = 'Enlace copiado.';
  } catch {
    if (status) status.textContent = 'No se pudo copiar el enlace.';
  }
}));
