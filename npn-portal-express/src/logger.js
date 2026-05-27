const fs = require('fs');
const path = require('path');
const util = require('util');

const logDir = process.env.LOG_DIR || path.join(__dirname, '..', 'logs');
if (!fs.existsSync(logDir)) fs.mkdirSync(logDir, { recursive: true });

const logStream = fs.createWriteStream(path.join(logDir, 'app.log'), { flags: 'a' });
const errorStream = fs.createWriteStream(path.join(logDir, 'error.log'), { flags: 'a' });

function timestamp() {
  return new Date().toISOString();
}

function formatArgs(args) {
  return args.map(a => (typeof a === 'string' ? a : util.inspect(a, { depth: 4 }))).join(' ');
}

const originalLog = console.log;
const originalError = console.error;

console.log = function (...args) {
  const line = `[${timestamp()}] ${formatArgs(args)}\n`;
  logStream.write(line);
  originalLog.apply(console, args);
};

console.error = function (...args) {
  const line = `[${timestamp()}] ERROR ${formatArgs(args)}\n`;
  errorStream.write(line);
  logStream.write(line);
  originalError.apply(console, args);
};

process.on('uncaughtException', (err) => {
  const line = `[${timestamp()}] UNCAUGHT EXCEPTION: ${err.stack || err}\n`;
  errorStream.write(line);
  logStream.write(line);
  originalError.call(console, 'Uncaught Exception:', err);
  process.exit(1);
});

process.on('unhandledRejection', (reason) => {
  const line = `[${timestamp()}] UNHANDLED REJECTION: ${reason instanceof Error ? reason.stack : reason}\n`;
  errorStream.write(line);
  logStream.write(line);
  originalError.call(console, 'Unhandled Rejection:', reason);
});
