
// src/controllers/phenophasesController.js
exports.getAll = (req, res) => {
  res.json({ message: "GET all phenophases" });
};

exports.getOne = (req, res) => {
  res.json({ message: "GET phenophases by ID", id: req.params.id });
};

exports.create = (req, res) => {
  res.json({ message: "POST new phenophases", body: req.body });
};

exports.update = (req, res) => {
  res.json({ message: "PUT update phenophases", id: req.params.id, body: req.body });
};

exports.delete = (req, res) => {
  res.json({ message: "DELETE phenophases", id: req.params.id });
};
