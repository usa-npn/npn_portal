
// src/controllers/observationsController.js
exports.getAll = (req, res) => {
  res.json({ message: "GET all observations" });
};

exports.getOne = (req, res) => {
  res.json({ message: "GET observations by ID", id: req.params.id });
};

exports.create = (req, res) => {
  res.json({ message: "POST new observations", body: req.body });
};

exports.update = (req, res) => {
  res.json({ message: "PUT update observations", id: req.params.id, body: req.body });
};

exports.delete = (req, res) => {
  res.json({ message: "DELETE observations", id: req.params.id });
};
