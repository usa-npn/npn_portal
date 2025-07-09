
// src/controllers/metadataController.js
exports.getAll = (req, res) => {
  res.json({ message: "GET all metadata" });
};

exports.getOne = (req, res) => {
  res.json({ message: "GET metadata by ID", id: req.params.id });
};

exports.create = (req, res) => {
  res.json({ message: "POST new metadata", body: req.body });
};

exports.update = (req, res) => {
  res.json({ message: "PUT update metadata", id: req.params.id, body: req.body });
};

exports.delete = (req, res) => {
  res.json({ message: "DELETE metadata", id: req.params.id });
};
