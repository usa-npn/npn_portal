
// src/controllers/speciesController.js
exports.getAll = (req, res) => {
  res.json({ message: "GET all species" });
};

exports.getOne = (req, res) => {
  res.json({ message: "GET species by ID", id: req.params.id });
};

exports.create = (req, res) => {
  res.json({ message: "POST new species", body: req.body });
};

exports.update = (req, res) => {
  res.json({ message: "PUT update species", id: req.params.id, body: req.body });
};

exports.delete = (req, res) => {
  res.json({ message: "DELETE species", id: req.params.id });
};
