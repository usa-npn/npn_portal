const checkProperty = require('../utils/checkProperty');
const validateUser = require('../utils/validateUser');

exports.exampleMethod = (req, res) => {
  const params = req.body;
  if (!checkProperty(params, 'some_required_param')) {
    return res.status(400).json({ error: 'Missing parameter' });
  }
  return res.json({ message: 'Handled logic for species controller' });
};