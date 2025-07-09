
// src/controllers/networksController.js
exports.getAll = (req, res) => {
  res.json({ message: "GET all networks" });
};

exports.getOne = (req, res) => {
  res.json({ message: "GET networks by ID", id: req.params.id });
};

exports.create = (req, res) => {
  res.json({ message: "POST new networks", body: req.body });
};

exports.update = (req, res) => {
  res.json({ message: "PUT update networks", id: req.params.id, body: req.body });
};

exports.delete = (req, res) => {
  res.json({ message: "DELETE networks", id: req.params.id });
};
