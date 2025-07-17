const express = require('express');
const app = express();

app.use(express.json());

app.use('/observations', require('./routes/observations'));
app.use('/person', require('./routes/person'));
app.use('/stations', require('./routes/stations'));
app.use('/phenophases', require('./routes/phenophases'));
app.use('/species', require('./routes/species'));
app.use('/enter_observation', require('./routes/enter_observation'));
app.use('/create_user', require('./routes/create_user'));
app.use('/networks', require('./routes/networks'));
app.use('/individuals', require('./routes/individuals'));
app.use('/badges', require('./routes/badges'));
app.use('/metadata', require('./routes/metadata'));
app.use('/create_station', require('./routes/create_station'));
app.use('/large', require('./routes/large'));
app.use('/create_individual', require('./routes/create_individual'));
app.use('/submissions', require('./routes/submissions'));
app.use('/wsdl', require('./routes/wsdl'));

app.get('/', (req, res) => {
  res.send('NPN Portal JS API (Converted from CakePHP)');
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`Server is running on port ${PORT}`);
});