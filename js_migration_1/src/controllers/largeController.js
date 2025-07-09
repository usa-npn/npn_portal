
// src/controllers/largeController.js
exports.getAll = (req, res) => {
  res.json({ message: "GET all large" });
};

exports.getOne = (req, res) => {
  res.json({ message: "GET large by ID", id: req.params.id });
};

exports.create = (req, res) => {
  res.json({ message: "POST new large", body: req.body });
};

exports.update = (req, res) => {
  res.json({ message: "PUT update large", id: req.params.id, body: req.body });
};

exports.delete = (req, res) => {
  res.json({ message: "DELETE large", id: req.params.id });
};
