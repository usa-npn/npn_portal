
// src/controllers/individualsController.js
exports.getAll = (req, res) => {
  res.json({ message: "GET all individuals" });
};

exports.getOne = (req, res) => {
  res.json({ message: "GET individuals by ID", id: req.params.id });
};

exports.create = (req, res) => {
  res.json({ message: "POST new individuals", body: req.body });
};

exports.update = (req, res) => {
  res.json({ message: "PUT update individuals", id: req.params.id, body: req.body });
};

exports.delete = (req, res) => {
  res.json({ message: "DELETE individuals", id: req.params.id });
};
