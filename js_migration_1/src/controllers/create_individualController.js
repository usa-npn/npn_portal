
// src/controllers/create_individualController.js
exports.getAll = (req, res) => {
  res.json({ message: "GET all create_individual" });
};

exports.getOne = (req, res) => {
  res.json({ message: "GET create_individual by ID", id: req.params.id });
};

exports.create = (req, res) => {
  res.json({ message: "POST new create_individual", body: req.body });
};

exports.update = (req, res) => {
  res.json({ message: "PUT update create_individual", id: req.params.id, body: req.body });
};

exports.delete = (req, res) => {
  res.json({ message: "DELETE create_individual", id: req.params.id });
};
